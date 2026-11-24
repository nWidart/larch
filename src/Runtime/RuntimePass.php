<?php

declare(strict_types=1);

namespace Nwidart\Larch\Runtime;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Nwidart\Larch\Graph\CallSite;
use Nwidart\Larch\Graph\Edge;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Graph\Graph;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

/**
 * Reads the booted app to find the links the code does not name: the class
 * behind a facade or a container key, the listeners of each event, and the
 * jobs a class dispatches.
 *
 * Services are never built. A binding is read from the closure the container
 * keeps: the class it was bound to, or the return type of a closure. A link
 * Larch cannot follow becomes an unknown edge, with its call site.
 */
final class RuntimePass
{
    private const int MAX_ALIAS_DEPTH = 10;

    /** @var array<string, array{class: string|null, boundIn: string|null}> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $app,
        private readonly Graph $graph,
        private readonly string $root,
    ) {}

    /**
     * @return list<Edge>
     */
    public function edges(): array
    {
        $edges = [];

        foreach ($this->graph->calls as $call) {
            $edge = match ($call->type) {
                CallSite::STATIC => $this->facade($call),
                CallSite::CONTAINER => $this->container($call),
                CallSite::DISPATCH, CallSite::STATIC_DISPATCH => $this->job($call),
            };

            if ($edge !== null) {
                $edges[] = $edge;
            }
        }

        return [...$edges, ...$this->listeners()];
    }

    private function facade(CallSite $call): ?Edge
    {
        $class = (string) $call->target;

        // Real-time facades: Facades\App\Pricing calls App\Pricing.
        if (str_starts_with($class, 'Facades\\')) {
            return $this->edge($call, EdgeKind::Facade, substr($class, 8), "the real-time facade {$class}");
        }

        if (str_starts_with($class, 'Illuminate\\') || ! $this->isFacade($class)) {
            return null;
        }

        try {
            $accessor = (new ReflectionMethod($class, 'getFacadeAccessor'))->invoke(null);
        } catch (Throwable) {
            return $this->unknown($call, "the facade {$class} has no accessor Larch can read");
        }

        if (is_object($accessor)) {
            return $this->edge($call, EdgeKind::Facade, $accessor::class, "the facade {$class}");
        }

        if (! is_string($accessor)) {
            return $this->unknown($call, "the facade {$class} has no accessor Larch can read");
        }

        // A class accessor is resolved through the container too: an
        // interface may be bound to another class.
        $binding = $this->binding(ltrim($accessor, '\\'));

        return $binding['class'] === null
            ? $this->unknown($call, "the facade {$class} resolves '{$accessor}', and Larch cannot tell its class without building it. Give the binding closure a return type", $binding['boundIn'])
            : $this->edge($call, EdgeKind::Facade, $binding['class'], "the facade {$class}", $binding['boundIn']);
    }

    private function container(CallSite $call): ?Edge
    {
        if ($call->target === null) {
            return $this->unknown($call, 'the container key is computed');
        }

        // A class key names its class in the code: the import edge has it.
        if ($this->isClass($call->target)) {
            return null;
        }

        $binding = $this->binding($call->target);

        if ($binding['class'] === null) {
            $why = $binding['boundIn'] === null
                ? "nothing binds '{$call->target}'"
                : "Larch cannot tell the class bound to '{$call->target}' without building it. Give the binding closure a return type";

            return $this->unknown($call, $why, $binding['boundIn']);
        }

        return $this->edge($call, EdgeKind::Container, $binding['class'], "the container key '{$call->target}'", $binding['boundIn']);
    }

    private function job(CallSite $call): ?Edge
    {
        if ($call->target === null) {
            return $this->unknown($call, 'the job is computed');
        }

        if ($call->type === CallSite::STATIC_DISPATCH
            && ! (class_exists($call->target) && in_array(Dispatchable::class, class_uses_recursive($call->target), true))) {
            return null;
        }

        return $this->edge($call, EdgeKind::Job, $call->target, null);
    }

    /**
     * One edge from each listener to the event it listens to.
     *
     * @return list<Edge>
     */
    private function listeners(): array
    {
        if (! $this->app->bound('events')) {
            return [];
        }

        $events = $this->app->make('events');

        if (! $events instanceof Dispatcher || ! method_exists($events, 'getRawListeners')) {
            return [];
        }

        $edges = [];

        /** @var array<array-key, list<mixed>> $raw */
        $raw = $events->getRawListeners();

        foreach ($raw as $event => $listeners) {
            // A numeric event name is an integer key.
            if (! is_string($event) || ! $this->isClass($event)) {
                continue;
            }

            foreach ($listeners as $listener) {
                $edge = $this->listenerEdge($event, $listener);

                if ($edge !== null) {
                    $edges[] = $edge;
                }
            }
        }

        foreach ($this->wildcards($events) as $pattern => $listeners) {
            foreach ($this->classesMatching($pattern) as $event) {
                foreach ($listeners as $listener) {
                    $edge = $this->listenerEdge($event, $listener);

                    if ($edge !== null) {
                        $edges[] = $edge;
                    }
                }
            }
        }

        return $edges;
    }

    /**
     * Listeners of patterns such as 'App\\Billing\\Events\\*', which
     * getRawListeners() leaves out.
     *
     * @return array<array-key, array<mixed>> pattern => listeners
     */
    private function wildcards(Dispatcher $events): array
    {
        if (! property_exists($events, 'wildcards')) {
            return [];
        }

        $wildcards = (new ReflectionProperty($events, 'wildcards'))->getValue($events);

        return is_array($wildcards) ? array_filter($wildcards, 'is_array') : [];
    }

    /**
     * The scanned classes a wildcard event name matches, as Laravel matches
     * it. A pattern needs a namespace before its first *: a pattern such as
     * '*' matches every event name, and Larch cannot tell the classes that
     * are events from the others.
     *
     * @return list<string>
     */
    private function classesMatching(string|int $pattern): array
    {
        $pattern = (string) $pattern;
        $prefix = strstr($pattern, '*', true);

        if ($prefix === false || ! str_contains($prefix, '\\')) {
            return [];
        }

        return array_values(array_filter(
            array_keys($this->graph->classes),
            fn (string $class): bool => Str::is($pattern, $class),
        ));
    }

    private function listenerEdge(string $event, mixed $listener): ?Edge
    {
        $boundIn = null;

        if ($listener instanceof Closure) {
            $function = new ReflectionFunction($listener);
            $class = $function->getClosureScopeClass()?->getName();
            $boundIn = $this->place((string) $function->getFileName(), (int) $function->getStartLine());
        } elseif (is_string($listener)) {
            $class = explode('@', $listener)[0];
        } elseif (is_array($listener) && isset($listener[0])) {
            $class = is_object($listener[0]) ? $listener[0]::class : (is_string($listener[0]) ? $listener[0] : null);
        } else {
            $class = null;
        }

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        [$file, $line] = $this->declaration($class);

        return new Edge($class, $event, EdgeKind::Event, $file, $line, null, $boundIn);
    }

    /**
     * The class a container key resolves to, and where it is bound.
     *
     * @return array{class: string|null, boundIn: string|null}
     */
    private function binding(string $key, int $depth = 0): array
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $abstract = $this->app->getAlias($key);
        $boundIn = $this->graph->bindings[$key] ?? $this->graph->bindings[$abstract] ?? null;

        if ($this->app instanceof Application && $this->app->isDeferredService($abstract)) {
            $this->app->loadDeferredProvider($abstract);
        }

        $instance = $this->instances()[$abstract] ?? null;

        if (is_object($instance)) {
            return $this->resolved[$key] = ['class' => $instance::class, 'boundIn' => $boundIn];
        }

        $binding = $this->app->getBindings()[$abstract] ?? null;
        $concrete = is_array($binding) ? ($binding['concrete'] ?? null) : null;

        if (! $concrete instanceof Closure) {
            return $this->resolved[$key] = ['class' => $this->isClass($abstract) ? $abstract : null, 'boundIn' => $boundIn];
        }

        $function = new ReflectionFunction($concrete);

        // bind('key', Concrete::class): the container wraps the name in a closure.
        if ($function->getFileName() === (new ReflectionClass(Container::class))->getFileName()) {
            $target = $function->getStaticVariables()['concrete'] ?? null;

            if (! is_string($target)) {
                return $this->resolved[$key] = ['class' => null, 'boundIn' => $boundIn];
            }

            if ($this->isClass($target) || $depth >= self::MAX_ALIAS_DEPTH || $target === $abstract) {
                return $this->resolved[$key] = ['class' => $this->isClass($target) ? ltrim($target, '\\') : null, 'boundIn' => $boundIn];
            }

            return $this->resolved[$key] = $this->binding($target, $depth + 1);
        }

        // bind('key', fn (): Concrete => ...): the return type names the class.
        $type = $function->getReturnType();
        $class = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

        return $this->resolved[$key] = [
            'class' => $class,
            'boundIn' => $this->place((string) $function->getFileName(), (int) $function->getStartLine()),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function instances(): array
    {
        $instances = (new ReflectionProperty(Container::class, 'instances'))->getValue($this->app);

        return is_array($instances) ? $instances : [];
    }

    private function edge(CallSite $call, EdgeKind $kind, string $to, ?string $through, ?string $boundIn = null): Edge
    {
        return new Edge($call->from, $to, $kind, $call->file, $call->line, $through, $boundIn);
    }

    private function unknown(CallSite $call, string $why, ?string $boundIn = null): Edge
    {
        return new Edge($call->from, '', EdgeKind::Unknown, $call->file, $call->line, "{$call->expression}: {$why}", $boundIn);
    }

    private function isFacade(string $class): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, Facade::class);
        } catch (Throwable) {
            return false;
        }
    }

    private function isClass(string $name): bool
    {
        try {
            return class_exists($name) || interface_exists($name);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{string, int}
     */
    private function declaration(string $class): array
    {
        if (isset($this->graph->classes[$class])) {
            return [$this->graph->classes[$class], $this->graph->lines[$class] ?? 0];
        }

        if (! class_exists($class)) {
            return ['', 0];
        }

        $reflection = new ReflectionClass($class);

        return [$this->relative((string) $reflection->getFileName()), (int) $reflection->getStartLine()];
    }

    private function place(string $file, int $line): string
    {
        return $this->relative($file).':'.$line;
    }

    private function relative(string $path): string
    {
        $root = rtrim($this->root, '/').'/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
