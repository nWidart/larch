<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Closure;

/**
 * What a rule found: everything that breaks it, and how to write the
 * message for the part the baseline does not accept.
 *
 * @template T of Breach
 */
final readonly class Result
{
    /**
     * @param  list<T>  $breaches
     * @param  Closure(list<T>): string  $report
     */
    public function __construct(
        public array $breaches,
        private Closure $report,
    ) {}

    /**
     * @param  list<T>  $breaches  a non-empty part of $this->breaches
     */
    public function report(array $breaches): string
    {
        return ($this->report)($breaches);
    }
}
