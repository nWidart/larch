<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Baseline\Fingerprint;

/**
 * A route, model, job or listener that breaks an app rule.
 */
final readonly class Finding implements Breach
{
    /**
     * @param  string  $place  "Modules/Billing/Models/CreditNote.php:7", or "POST api/refunds"
     * @param  list<string>  $lines  what is wrong, one line each
     */
    public function __construct(
        public string $place,
        public array $lines,
        private Fingerprint $fingerprint,
    ) {}

    public function fingerprint(): Fingerprint
    {
        return $this->fingerprint;
    }
}
