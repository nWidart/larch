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
}
