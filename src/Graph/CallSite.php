<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

/**
 * A call the runtime pass resolves on the booted app: a static call that
 * may be a facade, a call to the container, or the dispatch of a job.
 */
final readonly class CallSite
{
    public const string STATIC = 'static';

    public const string CONTAINER = 'container';

    public const string DISPATCH = 'dispatch';

    /**
     * Job::dispatch(): a job only if the class uses the Bus Dispatchable
     * trait. Events have a static dispatch() too.
     */
    public const string STATIC_DISPATCH = 'static-dispatch';

    /**
     * @param  self::STATIC|self::CONTAINER|self::DISPATCH|self::STATIC_DISPATCH  $type
     * @param  string|null  $target  the class or container key, or null when the code computes it
     * @param  string  $expression  the call as written, for unknown edges: "app($class)"
     */
    public function __construct(
        public string $type,
        public string $from,
        public ?string $target,
        public string $expression,
        public string $file,
        public int $line,
    ) {}
}
