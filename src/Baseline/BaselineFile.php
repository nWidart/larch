<?php

declare(strict_types=1);

namespace Nwidart\Larch\Baseline;

use JsonException;
use Nwidart\Larch\Exceptions\LarchException;

/**
 * Reads and writes larch-baseline.json: for each test, the violations it
 * accepts.
 *
 *     {
 *         "version": 1,
 *         "tests": {
 *             "Tests\\Arch\\ModulesTest > billing only uses public APIs": [
 *                 {"from": "Modules\\Billing\\...", "to": "Modules\\Catalog\\...", "kind": "import"}
 *             ]
 *         }
 *     }
 */
final class BaselineFile
{
    public const int VERSION = 1;

    /**
     * @return array<string, array<string, Fingerprint>> test => fingerprint key => fingerprint
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new LarchException("Larch could not read the baseline {$path}.");
        }

        return self::decode($path, $contents);
    }

    /**
     * Write the entries of the tests that ran, and keep those of every other
     * test. A test that completed gets exactly its fingerprints. A test that
     * stopped early may not have run all its checks, so its fingerprints are
     * added to the entries it had.
     *
     * Parallel workers each write their own tests: a lock file in the temp
     * directory, named after the baseline, holds the read and the write
     * together. It stays there, so every worker locks the same file. The new contents go
     * to a temporary file, renamed over the baseline once fully written, so
     * a failed write leaves the baseline as it was.
     *
     * @param  array<string, array{fingerprints: list<Fingerprint>, complete: bool}>  $updates  test => update
     */
    public static function write(string $path, array $updates): void
    {
        $lock = fopen(sys_get_temp_dir().'/larch-baseline-'.md5($path).'.lock', 'c');

        if ($lock === false) {
            throw new LarchException("Larch could not write the baseline {$path}.");
        }

        try {
            flock($lock, LOCK_EX);

            $tests = self::read($path);

            foreach ($updates as $test => $update) {
                $entries = $update['complete'] ? [] : ($tests[$test] ?? []);

                foreach ($update['fingerprints'] as $fingerprint) {
                    $entries[$fingerprint->key()] = $fingerprint;
                }

                if ($entries === []) {
                    unset($tests[$test]);
                } else {
                    $tests[$test] = $entries;
                }
            }

            self::replaceFile($path, self::encode($tests));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function replaceFile(string $path, string $contents): void
    {
        $temporary = $path.'.'.getmypid().'.tmp';

        if (file_put_contents($temporary, $contents) !== strlen($contents) || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new LarchException("Larch could not write the baseline {$path}.");
        }
    }

    /**
     * @param  array<string, array<string, Fingerprint>>  $tests
     */
    private static function encode(array $tests): string
    {
        ksort($tests);
        $data = [];

        foreach ($tests as $test => $fingerprints) {
            ksort($fingerprints);
            $data[$test] = array_values(array_map(fn (Fingerprint $fingerprint): array => $fingerprint->toArray(), $fingerprints));
        }

        return json_encode(
            ['version' => self::VERSION, 'tests' => (object) $data],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n";
    }

    /**
     * @return array<string, array<string, Fingerprint>>
     */
    private static function decode(string $path, string $contents): array
    {
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new LarchException("The baseline {$path} is not valid JSON: {$error->getMessage()}. Delete it and run Pest with --update-larch-baseline.", previous: $error);
        }

        if (! is_array($data) || ($data['version'] ?? null) !== self::VERSION || ! is_array($data['tests'] ?? null)) {
            throw new LarchException("The baseline {$path} is not a Larch baseline of version ".self::VERSION.'. Delete it and run Pest with --update-larch-baseline.');
        }

        $tests = [];

        foreach ($data['tests'] as $test => $entries) {
            if (! is_array($entries)) {
                throw self::badEntry($path, (string) $test);
            }

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! is_string($entry['from'] ?? null) || ! is_string($entry['to'] ?? null) || ! is_string($entry['kind'] ?? null)) {
                    throw self::badEntry($path, (string) $test);
                }

                $fingerprint = new Fingerprint($entry['from'], $entry['to'], $entry['kind']);
                $tests[(string) $test][$fingerprint->key()] = $fingerprint;
            }
        }

        return $tests;
    }

    private static function badEntry(string $path, string $test): LarchException
    {
        return new LarchException("The baseline {$path} has an entry for \"{$test}\" without from, to and kind.");
    }
}
