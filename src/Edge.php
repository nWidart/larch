<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Graph\EdgeKind;

/**
 * The kinds of link a rule checks, for via(): via(Edge::all()),
 * via(Edge::imports(), Edge::facades()).
 */
final readonly class Edge
{
    /**
     * @param  list<EdgeKind>  $kinds
     */
    private function __construct(public array $kinds) {}

    /**
     * Imports, facades, container keys, listeners and jobs.
     */
    public static function all(): self
    {
        return new self([EdgeKind::Import, EdgeKind::Facade, EdgeKind::Container, EdgeKind::Event, EdgeKind::Job]);
    }

    /**
     * Classes named in the code. The default.
     */
    public static function imports(): self
    {
        return new self([EdgeKind::Import]);
    }

    /**
     * Static calls on a facade, to the class behind it.
     */
    public static function facades(): self
    {
        return new self([EdgeKind::Facade]);
    }

    /**
     * app('key') and make('key'), to the class bound to the key.
     */
    public static function container(): self
    {
        return new self([EdgeKind::Container]);
    }

    /**
     * Listeners, to the events they listen to.
     */
    public static function events(): self
    {
        return new self([EdgeKind::Event]);
    }

    /**
     * Classes that dispatch a job, to the job.
     */
    public static function jobs(): self
    {
        return new self([EdgeKind::Job]);
    }
}
