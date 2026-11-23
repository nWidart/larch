<?php

declare(strict_types=1);

namespace Nwidart\Larch\Baseline;

use Nwidart\Larch\Rules\Violation;

/**
 * The baseline during one Pest run: which violations each test accepts,
 * which ones the run met, and, with --update-larch-baseline, the writing of
 * today's violations.
 *
 * State is static because it spans every test of the run, while the Larch
 * configuration may be set again in each test.
 */
final class Baseline
{
    private static bool $updating = false;

    /** @var array<string, array<string, array<string, Fingerprint>>> file => test => key => fingerprint */
    private static array $files = [];

    /** @var array<string, array<string, array<string, Fingerprint>>> file => test => key => fingerprint */
    private static array $seen = [];

    /** @var array<string, array<string, true>> file => tests with a new violation */
    private static array $failed = [];

    /** @var array<string, int> file => violations found in the baseline */
    private static array $matched = [];

    public static function update(bool $updating = true): void
    {
        self::$updating = $updating;
    }

    public static function isUpdating(): bool
    {
        return self::$updating;
    }

    /**
     * Forget the state of the run. For Larch's own tests.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$updating = false;
        self::$files = self::$seen = self::$failed = self::$matched = [];
    }

    /**
     * Record the violations a rule found in a test, and return those the
     * baseline does not accept. While updating, write them and return none.
     *
     * @param  string|null  $test  null outside a Pest test: nothing is accepted or recorded
     * @param  list<Violation>  $violations
     * @return list<Violation>
     */
    public static function check(string $file, ?string $test, array $violations): array
    {
        if ($test === null) {
            return $violations;
        }

        self::$seen[$file][$test] ??= [];

        foreach ($violations as $violation) {
            $fingerprint = $violation->link->fingerprint();
            self::$seen[$file][$test][$fingerprint->key()] = $fingerprint;
        }

        if (self::$updating) {
            BaselineFile::replace($file, $test, array_values(self::$seen[$file][$test]));

            return [];
        }

        $accepted = self::entries($file)[$test] ?? [];
        $new = array_values(array_filter(
            $violations,
            fn (Violation $violation): bool => ! isset($accepted[$violation->link->fingerprint()->key()]),
        ));

        self::$matched[$file] = (self::$matched[$file] ?? 0) + count($violations) - count($new);

        if ($new !== []) {
            self::$failed[$file][$test] = true;
        }

        return $new;
    }

    /**
     * What the run has to say about the baseline: what it wrote, how many
     * violations it accepted, and the entries no violation matched.
     *
     * @return list<string>
     */
    public static function summary(): array
    {
        $lines = [];

        foreach (self::$seen as $file => $tests) {
            $name = self::display($file);

            if (self::$updating) {
                $count = array_sum(array_map('count', $tests));
                $failing = count(array_filter($tests));
                $lines[] = 'Larch: wrote '.self::violations($count).' of '.$failing.' '.($failing === 1 ? 'test' : 'tests')." to the baseline {$name}.";

                continue;
            }

            $matched = self::$matched[$file] ?? 0;

            if ($matched > 0) {
                $lines[] = 'Larch: '.self::violations($matched).' '.($matched === 1 ? 'is' : 'are')." in the baseline {$name}.";
            }

            $gone = [];

            foreach ($tests as $test => $seen) {
                if (isset(self::$failed[$file][$test])) {
                    continue;
                }

                foreach (array_diff_key(self::entries($file)[$test] ?? [], $seen) as $fingerprint) {
                    $gone[] = '  '.$test.': '.$fingerprint->key();
                }
            }

            if ($gone !== []) {
                $count = count($gone);
                $lines[] = 'Larch: '.$count.' '.($count === 1 ? 'entry' : 'entries')." of the baseline {$name} no longer ".($count === 1 ? 'matches' : 'match').' a violation. Run Pest with --update-larch-baseline to remove '.($count === 1 ? 'it' : 'them').':';
                array_push($lines, ...$gone);
            }
        }

        return $lines;
    }

    /**
     * @return array<string, array<string, Fingerprint>>
     */
    private static function entries(string $file): array
    {
        return self::$files[$file] ??= BaselineFile::read($file);
    }

    private static function violations(int $count): string
    {
        return $count.' '.($count === 1 ? 'violation' : 'violations');
    }

    private static function display(string $file): string
    {
        $cwd = getcwd();

        return $cwd !== false && str_starts_with($file, $cwd.'/') ? substr($file, strlen($cwd) + 1) : $file;
    }
}
