<?php

declare(strict_types=1);

namespace Nwidart\Larch\Modules;

use Nwidart\Larch\Baseline\Fingerprint;
use Nwidart\Larch\Graph\Edge;
use Nwidart\Larch\Graph\EdgeKind;

/**
 * A class in one module that uses a class in another in one way (one kind of
 * edge), with every place it does.
 */
final readonly class ModuleLink
{
    public string $fromClass;

    public string $toClass;

    public EdgeKind $kind;

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
        $this->kind = $edges[0]->kind;
    }

    /**
     * What the baseline records of this link. No line, so an edit above
     * the call does not make the link new.
     */
    public function fingerprint(): Fingerprint
    {
        return new Fingerprint($this->fromClass, $this->toClass, $this->kind->value);
    }
}
