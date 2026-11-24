<?php

declare(strict_types=1);

namespace Nwidart\Larch\Mortise;

use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Rules\Result;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Rules\Violation;

/**
 * The rules of mortise.json, checked on imports as Mortise checks them
 * (mortise-core, boundary/links.rs and rules.rs):
 *
 * - A module that modules_statuses.json turns off breaks no rule, and no
 *   link to it does. Cycles are found among the modules that are on.
 * - A folder of the public surface is matched against the first segment of
 *   the namespace inside the module, in any case. A folder with a
 *   backslash, such as Internal\Api, matches no class.
 */
final readonly class MortisePreset
{
    public const string STATUSES_FILE = 'modules_statuses.json';

    public function __construct(private Larch $larch) {}

    /**
     * The result of each rule that is on, by its name in mortise.json.
     *
     * @return array<string, Result<Violation>>
     */
    public function results(): array
    {
        $config = MortiseConfig::read($this->larch->projectRoot());

        if (! $config->noCircularUse && ! $config->publicSurfaceOnly) {
            return [];
        }

        $off = $this->modulesOff();
        $modules = array_values(array_diff(array_keys($this->larch->moduleMap()), $off));
        $folders = array_values(array_filter($config->folders, fn (string $folder): bool => ! str_contains($folder, '\\')));
        $rules = new Rules($this->larch, [EdgeKind::Import], $folders, $off);
        $results = [];

        if ($config->noCircularUse) {
            $results['no-circular-use'] = $rules->noCycles($modules);
        }

        if ($config->publicSurfaceOnly) {
            $results['public-surface-only'] = $rules->onlyUsePublicApis($modules);
        }

        return $results;
    }

    /**
     * The modules modules_statuses.json at the project root turns off.
     * Mortise reads the file laravel-modules' file activator keeps; Larch
     * reads it at its default path.
     *
     * @return list<string>
     */
    private function modulesOff(): array
    {
        $path = $this->larch->projectRoot().'/'.self::STATUSES_FILE;
        $contents = is_file($path) ? file_get_contents($path) : false;
        $statuses = $contents === false ? null : json_decode($contents, true);

        if (! is_array($statuses)) {
            return [];
        }

        return array_map('strval', array_keys(array_filter($statuses, fn (mixed $on): bool => $on === false)));
    }
}
