<?php

declare(strict_types=1);

namespace Nwidart\Larch\Subjects;

/**
 * The public API of one module: the namespaces set with larch()->publicApi().
 */
final readonly class PublicApi
{
    public function __construct(public string $module) {}
}
