<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Baseline\Fingerprint;

/**
 * Something that breaks a rule, as the baseline records it.
 */
interface Breach
{
    public function fingerprint(): Fingerprint;
}
