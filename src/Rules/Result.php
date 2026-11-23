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
     * One result for several rules. The message has a part for each rule
     * that has something new, under its title.
     *
     * @param  array<string, Result<Violation>>  $results  title => result
     * @return self<Violation>
     */
    public static function combine(array $results): self
    {
        $breaches = [];

        foreach ($results as $result) {
            array_push($breaches, ...$result->breaches);
        }

        return new self($breaches, function (array $new) use ($results): string {
            $parts = [];

            foreach ($results as $title => $result) {
                $mine = array_values(array_filter($result->breaches, fn (Violation $breach): bool => in_array($breach, $new, true)));

                if ($mine !== []) {
                    $parts[] = $title."\n".$result->report($mine);
                }
            }

            return implode("\n\n", $parts);
        });
    }

    /**
     * @param  list<T>  $breaches  a non-empty part of $this->breaches
     */
    public function report(array $breaches): string
    {
        return ($this->report)($breaches);
    }
}
