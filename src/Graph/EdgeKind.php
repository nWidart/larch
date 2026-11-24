<?php

declare(strict_types=1);

namespace Nwidart\Larch\Graph;

/**
 * How one class reaches another.
 */
enum EdgeKind: string
{
    /**
     * The class is named in code: a use statement that is used, a type,
     * new, a static call, instanceof, catch, an attribute or ::class.
     */
    case Import = 'import';

    /**
     * A static call on a facade, to the class the facade resolves to.
     */
    case Facade = 'facade';

    /**
     * app('key'), resolve('key') or make('key') with a string key, to the
     * class the container binds to that key.
     */
    case Container = 'container';

    /**
     * A listener, to the event it listens to, as the booted app registers it.
     */
    case Event = 'event';

    /**
     * A class that dispatches a job, to the job.
     */
    case Job = 'job';

    /**
     * A call Larch cannot follow, such as app($class). The edge keeps its
     * call site and has no target class.
     */
    case Unknown = 'unknown';

    /**
     * The verb of the edge in messages: "A uses B", "A listens to B".
     */
    public function verb(): string
    {
        return match ($this) {
            self::Event => 'listens to',
            self::Job => 'dispatches',
            default => 'uses',
        };
    }
}
