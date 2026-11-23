<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

/**
 * One module, named as in larch()->modules(). Resolved when a rule runs.
 */
final readonly class Module
{
    public function __construct(public string $name) {}

    /**
     * The part of this module that other modules may use.
     */
    public function publicApi(): PublicApi
    {
        return new PublicApi($this->name);
    }
}
