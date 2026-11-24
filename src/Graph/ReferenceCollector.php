<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;
use SplObjectStorage;

/**
 * Collects the classes a file declares and every class each of them names.
 *
 * Runs after the NameResolver, so class names are fully qualified. Names of
 * functions and constants are skipped, and so is code outside a named class.
 *
 * Also collects the calls the runtime pass resolves (static calls, calls to
 * the container, dispatches of jobs) and the places that bind a container
 * key with a literal name.
 */
final class ReferenceCollector extends NodeVisitorAbstract
{
    private const array CONTAINER_FUNCTIONS = ['app', 'resolve'];

    private const array CONTAINER_METHODS = ['make', 'makewith'];

    private const array DISPATCH_FUNCTIONS = ['dispatch', 'dispatch_sync'];

    private const array DISPATCH_METHODS = ['dispatch', 'dispatchsync', 'dispatchif', 'dispatchunless', 'dispatchafterresponse'];

    private const array BIND_METHODS = ['bind', 'bindif', 'singleton', 'singletonif', 'scoped', 'scopedif', 'instance'];

    /** The App facade, and its default alias. */
    private const array APP_FACADES = ['Illuminate\Support\Facades\App', 'App'];

    /** The Bus facade, and its default alias. */
    private const array BUS_FACADES = ['Illuminate\Support\Facades\Bus', 'Bus'];

    /** @var list<string> */
    public array $declared = [];

    /** @var array<string, int> class => line of its declaration */
    public array $lines = [];

    /** @var list<array{from: string, to: string, line: int}> */
    public array $references = [];

    /** @var list<array{type: CallSite::STATIC|CallSite::CONTAINER|CallSite::DISPATCH|CallSite::STATIC_DISPATCH, from: string, target: string|null, expression: string, line: int}> */
    public array $calls = [];

    /** @var array<string, int> container key => line that binds it */
    public array $bindings = [];

    /** @var list<string> */
    private array $classStack = [];

    /** @var SplObjectStorage<Node, true> */
    private SplObjectStorage $skip;

    /**
     * @param  string  $code  the source of the file, to quote calls as written
     */
    public function __construct(private readonly string $code = '')
    {
        $this->skip = new SplObjectStorage;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof ClassLike && $node->namespacedName !== null) {
            $class = $node->namespacedName->toString();
            $this->declared[] = $class;
            $this->lines[$class] = $node->getStartLine();
            $this->classStack[] = $class;

            return null;
        }

        if ($node instanceof FuncCall || $node instanceof ConstFetch) {
            if ($node->name instanceof Node\Name) {
                $this->skip[$node->name] = true;
            }

            if ($node instanceof FuncCall) {
                $this->collectFunctionCall($node);
            }

            return null;
        }

        if ($node instanceof StaticCall) {
            $this->collectStaticCall($node);
        }

        if ($node instanceof MethodCall) {
            $this->collectMethodCall($node);
        }

        if ($node instanceof FullyQualified && ! isset($this->skip[$node]) && $this->classStack !== []) {
            $from = $this->currentClass();
            $to = $node->toString();

            if ($to !== $from) {
                $this->references[] = ['from' => $from, 'to' => $to, 'line' => $node->getStartLine()];
            }
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof ClassLike && $node->namespacedName !== null) {
            array_pop($this->classStack);
        }

        return null;
    }

    private function collectFunctionCall(FuncCall $call): void
    {
        if (! $call->name instanceof Node\Name || $call->isFirstClassCallable() || $this->classStack === []) {
            return;
        }

        $lower = $this->globalFunction($call->name);

        if ($lower === null) {
            return;
        }

        $argument = $this->firstArgument($call);

        if ($argument === null) {
            return;
        }

        if (in_array($lower, self::CONTAINER_FUNCTIONS, true)) {
            $this->call(CallSite::CONTAINER, $this->key($argument), $call);
        } elseif (in_array($lower, self::DISPATCH_FUNCTIONS, true)) {
            $this->call(CallSite::DISPATCH, $this->newClass($argument), $call);
        }
    }

    private function collectStaticCall(StaticCall $call): void
    {
        if (! $call->class instanceof FullyQualified || ! $call->name instanceof Identifier || $call->isFirstClassCallable() || $this->classStack === []) {
            return;
        }

        $class = $call->class->toString();
        $method = $call->name->toLowerString();
        $argument = $this->firstArgument($call);

        if (in_array($class, self::APP_FACADES, true) && in_array($method, self::CONTAINER_METHODS, true) && $argument !== null) {
            $this->call(CallSite::CONTAINER, $this->key($argument), $call);

            return;
        }

        if (in_array($class, self::BUS_FACADES, true) && in_array($method, self::DISPATCH_METHODS, true) && $argument !== null) {
            $this->call(CallSite::DISPATCH, $this->newClass($argument), $call);

            return;
        }

        // Every static call may be a facade, and Job::dispatch() a job.
        if (in_array($method, self::DISPATCH_METHODS, true)) {
            $this->call(CallSite::STATIC_DISPATCH, $class, $call);
        }

        $this->call(CallSite::STATIC, $class, $call);
    }

    private function collectMethodCall(MethodCall $call): void
    {
        if (! $call->name instanceof Identifier || $call->isFirstClassCallable()) {
            return;
        }

        $method = $call->name->toLowerString();
        $argument = $this->firstArgument($call);

        if ($argument === null) {
            return;
        }

        if (in_array($method, self::BIND_METHODS, true)) {
            $key = $this->key($argument);

            if ($key !== null) {
                $this->bindings[$key] ??= $call->getStartLine();
            }

            return;
        }

        if (in_array($method, self::CONTAINER_METHODS, true) && $this->classStack !== [] && $this->isContainer($call->var)) {
            $this->call(CallSite::CONTAINER, $this->key($argument), $call);
        }
    }

    /**
     * The lowercase name of a global function the call reaches, or null when
     * it reaches a namespaced function: one imported with "use function", or
     * one declared in the namespace of the file, which PHP tries first.
     */
    private function globalFunction(Node\Name $name): ?string
    {
        if ($name instanceof FullyQualified) {
            return $name->isUnqualified() ? $name->toLowerString() : null;
        }

        $namespaced = $name->getAttribute('namespacedName');

        if ($namespaced instanceof Node\Name && ! $namespaced->isUnqualified() && function_exists($namespaced->toString())) {
            return null;
        }

        return $name->isUnqualified() ? $name->toLowerString() : null;
    }

    /**
     * app(), $this->app, $app and $container.
     */
    private function isContainer(Expr $expression): bool
    {
        return ($expression instanceof FuncCall && $expression->name instanceof Node\Name && in_array($this->globalFunction($expression->name), self::CONTAINER_FUNCTIONS, true))
            || ($expression instanceof PropertyFetch && $expression->name instanceof Identifier && $expression->name->toString() === 'app')
            || ($expression instanceof Variable && in_array($expression->name, ['app', 'container'], true));
    }

    /**
     * @param  CallSite::STATIC|CallSite::CONTAINER|CallSite::DISPATCH|CallSite::STATIC_DISPATCH  $type
     */
    private function call(string $type, ?string $target, Expr $call): void
    {
        $this->calls[] = [
            'type' => $type,
            'from' => $this->currentClass(),
            'target' => $target,
            'expression' => $this->print($call),
            'line' => $call->getStartLine(),
        ];
    }

    /**
     * A string key or a class named with ::class. Null when computed.
     */
    private function key(Expr $argument): ?string
    {
        if ($argument instanceof String_) {
            return ltrim($argument->value, '\\');
        }

        if ($argument instanceof ClassConstFetch && $argument->class instanceof FullyQualified
            && $argument->name instanceof Identifier && $argument->name->toLowerString() === 'class') {
            return $argument->class->toString();
        }

        return null;
    }

    /**
     * The class of new Job(...). Null for anything else.
     */
    private function newClass(Expr $argument): ?string
    {
        return $argument instanceof New_ && $argument->class instanceof FullyQualified ? $argument->class->toString() : null;
    }

    private function firstArgument(FuncCall|MethodCall|StaticCall $call): ?Expr
    {
        $first = $call->getArgs()[0] ?? null;

        return $first instanceof Arg ? $first->value : null;
    }

    private function currentClass(): string
    {
        $class = end($this->classStack);

        return $class === false ? '' : $class;
    }

    private function print(Expr $expression): string
    {
        $start = $expression->getStartFilePos();
        $end = $expression->getEndFilePos();

        return $start >= 0 && $end >= $start ? substr($this->code, $start, $end - $start + 1) : '';
    }
}
