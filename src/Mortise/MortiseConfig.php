<?php

declare(strict_types=1);

namespace Nwidart\Larch\Mortise;

use JsonException;
use Nwidart\Larch\Exceptions\LarchException;

/**
 * The rules of mortise.json, the file Mortise keeps in the app folder:
 *
 *     {
 *         "rules": {
 *             "no-circular-use": true,
 *             "public-surface-only": { "enabled": true, "folders": ["Contracts", "Events"] }
 *         }
 *     }
 *
 * Read as Mortise reads it (mortise-core, boundary/rules.rs): no file, no
 * "rules" key or a missing key means the default. Where Mortise falls back
 * to the defaults on a broken file, Larch stops, so CI does not pass on
 * rules nobody wrote.
 */
final readonly class MortiseConfig
{
    public const string FILE = 'mortise.json';

    /**
     * @param  list<string>  $folders  for a module without a public surface: the folders other modules may use
     */
    public function __construct(
        public bool $noCircularUse = true,
        public bool $publicSurfaceOnly = true,
        public array $folders = ['Contracts', 'Events'],
    ) {}

    public static function read(string $root): self
    {
        $path = rtrim($root, '/').'/'.self::FILE;

        if (! is_file($path)) {
            return new self;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new LarchException("Larch could not read {$path}.");
        }

        try {
            $file = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new LarchException("{$path} is not valid JSON: {$error->getMessage()}.", previous: $error);
        }

        if (! is_array($file) || array_is_list($file) && $file !== []) {
            throw self::invalid($path, 'the file is not a JSON object');
        }

        $rules = $file['rules'] ?? [];

        if (! is_array($rules)) {
            throw self::invalid($path, '"rules" is not an object');
        }

        $cycles = $rules['no-circular-use'] ?? true;

        if (! is_bool($cycles)) {
            throw self::invalid($path, '"no-circular-use" is not true or false');
        }

        $surface = $rules['public-surface-only'] ?? [];

        if (! is_array($surface)) {
            throw self::invalid($path, '"public-surface-only" is not an object');
        }

        $enabled = $surface['enabled'] ?? true;
        $folders = $surface['folders'] ?? ['Contracts', 'Events'];

        if (! is_bool($enabled)) {
            throw self::invalid($path, '"public-surface-only.enabled" is not true or false');
        }

        if (! is_array($folders) || ! array_is_list($folders) || array_filter($folders, fn (mixed $folder): bool => ! is_string($folder)) !== []) {
            throw self::invalid($path, '"public-surface-only.folders" is not a list of folder names');
        }

        /** @var list<string> $folders */
        return new self($cycles, $enabled, $folders);
    }

    private static function invalid(string $path, string $why): LarchException
    {
        return new LarchException("{$path} does not hold Mortise rules: {$why}.");
    }
}
