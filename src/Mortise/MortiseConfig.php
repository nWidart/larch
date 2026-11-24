<?php

declare(strict_types=1);

namespace Nwidart\Larch\Mortise;

use JsonException;
use Nwidart\Larch\Exceptions\LarchException;
use stdClass;

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
            // A folder or a broken link at the path is not a missing file.
            if (file_exists($path) || is_link($path)) {
                throw new LarchException("Larch could not read {$path}.");
            }

            return new self;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new LarchException("Larch could not read {$path}.");
        }

        try {
            // Objects stay objects, so {"0": "Pricing"} is not a list.
            $file = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new LarchException("{$path} is not valid JSON: {$error->getMessage()}.", previous: $error);
        }

        if (! $file instanceof stdClass) {
            throw self::invalid($path, 'the file is not a JSON object');
        }

        // A missing key is the default. A key that is there must be valid,
        // null included.
        $rules = property_exists($file, 'rules') ? $file->rules : new stdClass;

        if (! $rules instanceof stdClass) {
            throw self::invalid($path, '"rules" is not an object');
        }

        $cycles = property_exists($rules, 'no-circular-use') ? $rules->{'no-circular-use'} : true;

        if (! is_bool($cycles)) {
            throw self::invalid($path, '"no-circular-use" is not true or false');
        }

        $surface = property_exists($rules, 'public-surface-only') ? $rules->{'public-surface-only'} : new stdClass;

        if (! $surface instanceof stdClass) {
            throw self::invalid($path, '"public-surface-only" is not an object');
        }

        $enabled = property_exists($surface, 'enabled') ? $surface->enabled : true;
        $folders = property_exists($surface, 'folders') ? $surface->folders : ['Contracts', 'Events'];

        if (! is_bool($enabled)) {
            throw self::invalid($path, '"public-surface-only.enabled" is not true or false');
        }

        if (! is_array($folders) || array_filter($folders, fn (mixed $folder): bool => ! is_string($folder)) !== []) {
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
