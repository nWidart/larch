<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Larch;
use Nwidart\Larch\Modules\ModuleLink;
use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\PublicApi;

/**
 * The module rules. Each returns the message of the failure, or null.
 *
 * Only links between two modules are checked. Links to vendor classes and
 * to code outside every module are not.
 */
final readonly class Rules
{
    public function __construct(private Larch $larch) {}

    /**
     * The module uses only the given modules, or their public API.
     *
     * @param  list<Module|PublicApi>  $targets
     */
    public function onlyDependOn(Module $subject, array $targets): ?string
    {
        $this->larch->namespaceOf($subject->name);

        $whole = [];
        $api = [];

        foreach ($targets as $target) {
            if ($target instanceof Module) {
                $this->larch->namespaceOf($target->name);
                $whole[$target->name] = true;
            } else {
                $this->larch->namespaceOf($target->module);
                $api[$target->module] = true;
            }
        }

        $violations = [];

        foreach ($this->linksFrom([$subject->name]) as $link) {
            if (isset($whole[$link->toModule])) {
                continue;
            }

            if (isset($api[$link->toModule])) {
                if (! $this->larch->isPublic($link->toClass, $link->toModule)) {
                    $violations[] = new Violation($link, $this->internalReason($link));
                }

                continue;
            }

            $violations[] = new Violation($link, "{$subject->name} may not depend on {$link->toModule}.");
        }

        if ($violations === []) {
            return null;
        }

        $allowed = array_merge(
            array_keys($whole),
            array_map(fn (string $module): string => $module.' (public API)', array_keys($api)),
        );
        $allowedText = $allowed === [] ? 'no other module' : implode(', ', $allowed);

        return Report::violations("Module {$subject->name} may only depend on {$allowedText}.", $violations);
    }

    /**
     * The given modules use other modules only through their public API.
     *
     * @param  list<string>  $modules
     */
    public function onlyUsePublicApis(array $modules): ?string
    {
        $violations = [];

        foreach ($this->linksFrom($modules) as $link) {
            if (! $this->larch->isPublic($link->toClass, $link->toModule)) {
                $violations[] = new Violation($link, $this->internalReason($link));
            }
        }

        if ($violations === []) {
            return null;
        }

        $who = count($modules) === 1 ? "Module {$modules[0]} uses" : 'Modules use';

        return Report::violations("{$who} other modules only through their public API.", $violations);
    }

    /**
     * No module uses another that, directly or not, uses it back.
     *
     * @param  list<string>  $modules  report only cycles that hold one of these
     */
    public function noCycles(array $modules): ?string
    {
        $links = $this->larch->links();
        $adjacency = [];

        foreach (array_keys($this->larch->moduleMap()) as $module) {
            $adjacency[$module] = [];
        }

        foreach ($links as $link) {
            $adjacency[$link->fromModule][$link->toModule] = true;
        }

        $cycles = array_values(array_filter(
            StronglyConnected::components($adjacency),
            fn (array $component): bool => count($component) > 1 && array_intersect($component, $modules) !== [],
        ));

        return $cycles === [] ? null : Report::cycles($cycles, $links);
    }

    /**
     * @param  list<string>  $modules
     * @return list<ModuleLink>
     */
    private function linksFrom(array $modules): array
    {
        return array_values(array_filter(
            $this->larch->links(),
            fn (ModuleLink $link): bool => in_array($link->fromModule, $modules, true),
        ));
    }

    private function internalReason(ModuleLink $link): string
    {
        $namespaces = $this->larch->publicApiNamespaces();
        $api = $namespaces === [] ? 'empty' : implode(', ', $namespaces);

        return "It is internal to {$link->toModule}. The public API of a module is: {$api}.";
    }
}
