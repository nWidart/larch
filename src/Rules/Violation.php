<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Baseline\Fingerprint;
use Nwidart\Larch\Modules\ModuleLink;

/**
 * A link that breaks a rule, and why.
 */
final readonly class Violation implements Breach
{
    public function __construct(
        public ModuleLink $link,
        public string $reason,
    ) {}

    public function fingerprint(): Fingerprint
    {
        return $this->link->fingerprint();
    }
}
