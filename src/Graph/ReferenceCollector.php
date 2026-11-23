<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;
use SplObjectStorage;

/**
 * Collects the classes a file declares and every class each of them names.
 *
 * Runs after the NameResolver, so class names are fully qualified. Names of
 * functions and constants are skipped, and so is code outside a named class.
 */
final class ReferenceCollector extends NodeVisitorAbstract
{
    /** @var list<string> */
    public array $declared = [];

    /** @var list<array{from: string, to: string, line: int}> */
    public array $references = [];

    /** @var list<string> */
    private array $classStack = [];

    /** @var SplObjectStorage<Node, true> */
    private SplObjectStorage $skip;

    public function __construct()
    {
        $this->skip = new SplObjectStorage;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof ClassLike && $node->namespacedName !== null) {
            $class = $node->namespacedName->toString();
            $this->declared[] = $class;
            $this->classStack[] = $class;

            return null;
        }

        if ($node instanceof FuncCall || $node instanceof ConstFetch) {
            if ($node->name instanceof Node\Name) {
                $this->skip[$node->name] = true;
            }

            return null;
        }

        if ($node instanceof FullyQualified && ! isset($this->skip[$node]) && $this->classStack !== []) {
            $from = $this->classStack[array_key_last($this->classStack)];
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
}
