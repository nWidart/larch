<?php

declare(strict_types=1);

namespace Nwidart\Larch\Baseline;

use Nwidart\Larch\Rules\Breach;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Exceptions\InvalidPestCommand;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

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

    private static bool $committed = false;

    /** @var array<string, array<string, array<string, Fingerprint>>> file => test => key => fingerprint */
    private static array $files = [];

    /** @var array<string, array<string, array<string, Fingerprint>>> file => test => key => fingerprint */
    private static array $seen = [];

    /** @var array<string, array<string, TestCase>> file => test name => test */
    private static array $tests = [];

    /** @var array<string, int> file => violations found in the baseline */
    private static array $matched = [];

    /**
     * Write the baseline from this run. It is written when Pest prints its
     * summary, or, in a parallel worker that prints none, when PHP exits.
     */
    public static function update(): void
    {
        if (! self::$updating) {
            self::$updating = true;
            register_shutdown_function(self::commit(...));
        }
    }

    /**
     * Record the violations a rule found in the running test, and return
     * those the baseline does not accept. While updating, return none: the
     * baseline is written once the tests are done.
     *
     * Outside a Pest test, nothing is accepted or recorded.
     *
     * @template T of Breach
     *
     * @param  list<T>  $violations
     * @return list<T>
     */
    public static function check(string $file, array $violations): array
    {
        $test = self::currentTest();

        if ($test === null) {
            return $violations;
        }

        $name = $test::getPrintableTestCaseName().' > '.$test->getPrintableTestCaseMethodName();
        self::$tests[$file][$name] = $test;
        self::$seen[$file][$name] ??= [];

        foreach ($violations as $violation) {
            $fingerprint = $violation->fingerprint();
            self::$seen[$file][$name][$fingerprint->key()] = $fingerprint;
        }

        if (self::$updating) {
            return [];
        }

        $accepted = self::entries($file)[$name] ?? [];
        $new = array_values(array_filter(
            $violations,
            fn (Breach $violation): bool => ! isset($accepted[$violation->fingerprint()->key()]),
        ));

        self::$matched[$file] = (self::$matched[$file] ?? 0) + count($violations) - count($new);

        return $new;
    }

    /**
     * Write what the tests found to the baseline, once. A test that did not
     * pass keeps the entries it had, as it may have stopped before a check.
     */
    private static function commit(): void
    {
        if (! self::$updating || self::$committed) {
            return;
        }

        self::$committed = true;

        foreach (self::$seen as $file => $tests) {
            $updates = [];

            foreach ($tests as $name => $fingerprints) {
                $updates[$name] = [
                    'fingerprints' => array_values($fingerprints),
                    'complete' => self::passed(self::$tests[$file][$name]),
                ];
            }

            BaselineFile::write($file, $updates);
        }
    }

    /**
     * What the run has to say about the baseline: what it wrote, how many
     * violations it accepted, and the entries no violation matched.
     *
     * @return list<string>
     */
    public static function summary(): array
    {
        self::commit();

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

            foreach ($tests as $name => $seen) {
                // A test that did not pass may have stopped before a check.
                if (! self::passed(self::$tests[$file][$name])) {
                    continue;
                }

                foreach (array_diff_key(self::entries($file)[$name] ?? [], $seen) as $fingerprint) {
                    $gone[] = '  '.$name.': '.$fingerprint->key();
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
     * The running Pest test. Null outside one, or when PHPUnit runs the
     * tests without Pest.
     *
     * @return (TestCase&HasPrintableTestCaseName)|null
     */
    private static function currentTest(): ?TestCase
    {
        try {
            $test = TestSuite::getInstance()->test;
        } catch (InvalidPestCommand) {
            return null;
        }

        return $test instanceof HasPrintableTestCaseName ? $test : null;
    }

    private static function passed(TestCase $test): bool
    {
        return $test->status()->isSuccess();
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
