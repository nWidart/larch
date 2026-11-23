<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Closure;

/**
 * What a rule found: every violation, and how to write the message for the
 * ones the baseline does not accept.
 */
final readonly class Result
{
    /**
     * @param  list<Violation>  $violations
     * @param  Closure(list<Violation>): string  $report
     */
    public function __construct(
        public array $violations,
        private Closure $report,
    ) {}

    public static function passed(): self
    {
        return new self([], fn (): string => '');
    }

    /**
     * @param  list<Violation>  $violations  a non-empty part of $this->violations
     */
    public function report(array $violations): string
    {
        return ($this->report)($violations);
    }
}
