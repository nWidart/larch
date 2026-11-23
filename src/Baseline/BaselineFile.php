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
     * Replace the entries of one test, and keep those of every other test.
     * The file is locked while it is read and written, so parallel workers
     * can each write their own tests.
     *
     * @param  list<Fingerprint>  $fingerprints
     */
    public static function replace(string $path, string $test, array $fingerprints): void
    {
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new LarchException("Larch could not write the baseline {$path}.");
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $tests = $contents === false || trim($contents) === '' ? [] : self::decode($path, $contents);

            $tests[$test] = [];

            foreach ($fingerprints as $fingerprint) {
                $tests[$test][$fingerprint->key()] = $fingerprint;
            }

            if ($tests[$test] === []) {
                unset($tests[$test]);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, self::encode($tests));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
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
