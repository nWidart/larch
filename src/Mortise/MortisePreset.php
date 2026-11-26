<?php

declare(strict_types=1);

namespace Nwidart\Larch\Mortise;

use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Rules\Result;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Rules\Violation;
use Nwidart\Larch\Subjects\Module;

/**
 * The rules of mortise.json, checked on imports as Mortise checks them
 * (mortise-core, boundary/links.rs and rules.rs):
 *
 * - A module that modules_statuses.json turns off breaks no rule, and no
 *   link to it does. Cycles are found among the modules that are on.
 * - A folder of the public surface is matched against the first segment of
 *   the namespace inside the module, in any case. A folder with a
 *   backslash, such as Internal\Api, matches no class.
 * - Under allowed-use, a module with a list uses only the modules of its
 *   list; a module without a list is not checked. A name that is no module
 *   of the app breaks nothing. Mortise warns on it; a Pest test cannot.
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

        if (! $config->noCircularUse && ! $config->publicSurfaceOnly && $config->allowedUse === null) {
            return [];
        }

        $off = $this->modulesOff();
        $all = array_keys($this->larch->moduleMap());
        $modules = array_values(array_diff($all, $off));
        $folders = array_values(array_filter($config->folders, fn (string $folder): bool => ! str_contains($folder, '\\')));
        $rules = new Rules($this->larch, [EdgeKind::Import], $folders, $off);
        $results = [];

        if ($config->noCircularUse) {
            $results['no-circular-use'] = $rules->noCycles($modules);
        }

        if ($config->publicSurfaceOnly) {
            $results['public-surface-only'] = $rules->onlyUsePublicApis($modules);
        }

        if ($config->allowedUse !== null) {
            $results['allowed-use'] = $this->allowedUse($rules, $config->allowedUse, $all);
        }

        return $results;
    }

    /**
     * toOnlyDependOn() for each module with a list, as one rule. A name
     * that is no module is left out, so it breaks nothing.
     *
     * @param  array<string, list<string>>  $lists
     * @param  list<string>  $all  the modules of the app, on or off
     * @return Result<Violation>
     */
    private function allowedUse(Rules $rules, array $lists, array $all): Result
    {
        $results = [];

        foreach ($lists as $module => $list) {
            if (in_array($module, $all, true)) {
                $targets = array_map(fn (string $name): Module => new Module($name), array_values(array_intersect($list, $all)));
                $results[] = $rules->onlyDependOn(new Module($module), $targets);
            }
        }

        $breaches = array_merge(...array_map(fn (Result $result): array => $result->breaches, $results));

        // The message of each module, for the breaks the baseline does not accept.
        return new Result($breaches, fn (array $new): string => implode("\n\n", array_filter(array_map(
            function (Result $result) use ($new): string {
                $part = array_values(array_filter($result->breaches, fn (Violation $violation): bool => in_array($violation, $new, true)));

                return $part === [] ? '' : $result->report($part);
            },
            $results,
        ))));
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
