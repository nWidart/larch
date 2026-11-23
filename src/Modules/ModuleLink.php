<?php

declare(strict_types=1);

namespace Nwidart\Larch\Modules;

use Nwidart\Larch\Graph\Edge;

/**
 * A class in one module that uses a class in another, with every place it does.
 */
final readonly class ModuleLink
{
    public string $fromClass;

    public string $toClass;

    /**
     * @param  non-empty-list<Edge>  $edges
     */
    public function __construct(
        public string $fromModule,
        public string $toModule,
        public array $edges,
    ) {
        $this->fromClass = $edges[0]->from;
        $this->toClass = $edges[0]->to;
    }
}
