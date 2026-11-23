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
     */
    public function __construct(
        public array $classes,
        public array $edges,
    ) {}

    public function has(string $class): bool
    {
        return isset($this->classes[$class]);
    }
}
