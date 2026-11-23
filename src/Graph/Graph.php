<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

/**
 * The classes of an app and every link between them.
 */
final readonly class Graph
{
    /**
     * @param  array<string, string>  $classes  class name => file, relative to the project root
     * @param  list<Edge>  $edges
     * @param  list<CallSite>  $calls  calls the runtime pass resolves
     * @param  array<string, string>  $bindings  container key => where code binds it, "file:line"
     * @param  array<string, int>  $lines  class name => line of its declaration
     */
    public function __construct(
        public array $classes,
        public array $edges,
        public array $calls = [],
        public array $bindings = [],
        public array $lines = [],
    ) {}

    public function has(string $class): bool
    {
        return isset($this->classes[$class]);
    }

    /**
     * @param  list<Edge>  $edges
     */
    public function withEdges(array $edges): self
    {
        return new self($this->classes, [...$this->edges, ...$edges], $this->calls, $this->bindings, $this->lines);
    }

    /**
     * @param  list<EdgeKind>  $kinds
     * @return list<Edge>
     */
    public function edgesOf(array $kinds): array
    {
        return array_values(array_filter($this->edges, fn (Edge $edge): bool => in_array($edge->kind, $kinds, true)));
    }
}
