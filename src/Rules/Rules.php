<?php

declare(strict_types=1);

namespace Nwidart\Larch\Rules;

use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Modules\ModuleLink;
use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\PublicApi;

/**
 * The module rules. Each returns what it found as a Result.
 *
 * Only links between two modules are checked. Links to vendor classes and
 * to code outside every module are not.
 */
final readonly class Rules
{
    /**
     * @param  list<EdgeKind>  $kinds  the kinds of link the rules check
     */
    public function __construct(
        private Larch $larch,
        private array $kinds = [EdgeKind::Import],
    ) {}

    /**
     * The module uses only the given modules, or their public API.
     *
     * @param  list<Module|PublicApi>  $targets
     */
    public function onlyDependOn(Module $subject, array $targets): Result
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
            return Result::passed();
        }

        $allowed = array_merge(
            array_keys($whole),
            array_map(fn (string $module): string => $module.' (public API)', array_keys($api)),
        );
        $allowedText = $allowed === [] ? 'no other module' : implode(', ', $allowed);

        return new Result($violations, fn (array $new): string => Report::violations("Module {$subject->name} may only depend on {$allowedText}.", $new));
    }

    /**
     * The given modules use other modules only through their public API.
     *
     * @param  list<string>  $modules
     */
    public function onlyUsePublicApis(array $modules): Result
    {
        $violations = [];

        foreach ($this->linksFrom($modules) as $link) {
            if (! $this->larch->isPublic($link->toClass, $link->toModule)) {
                $violations[] = new Violation($link, $this->internalReason($link));
            }
        }

        if ($violations === []) {
            return Result::passed();
        }

        $who = count($modules) === 1 ? "Module {$modules[0]} uses" : 'Modules use';

        return new Result($violations, fn (array $new): string => Report::violations("{$who} other modules only through their public API.", $new));
    }

    /**
     * No module uses another that, directly or not, uses it back.
     *
     * @param  list<string>  $modules  report only cycles that hold one of these
     */
    public function noCycles(array $modules): Result
    {
        $links = $this->larch->links($this->kinds);
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

        $violations = [];

        foreach ($links as $link) {
            if (self::cycleOf($cycles, $link) !== null) {
                $violations[] = new Violation($link, "{$link->fromModule} and {$link->toModule} are in a cycle.");
            }
        }

        if ($violations === []) {
            return Result::passed();
        }

        // A cycle is reported when one of its links is new, with all its links.
        return new Result($violations, fn (array $new): string => Report::cycles(
            array_values(array_filter(
                $cycles,
                fn (array $cycle): bool => array_filter($new, fn (Violation $violation): bool => self::cycleOf([$cycle], $violation->link) !== null) !== [],
            )),
            $links,
        ));
    }

    /**
     * No class of the module listens to an event of the target modules.
     *
     * @param  list<Module>  $targets
     */
    public function ignoreEventsOf(Module $subject, array $targets): Result
    {
        $this->larch->namespaceOf($subject->name);
        $names = [];

        foreach ($targets as $target) {
            $this->larch->namespaceOf($target->name);
            $names[] = $target->name;
        }

        $violations = [];

        foreach ($this->linksFrom([$subject->name]) as $link) {
            if ($link->kind === EdgeKind::Event && in_array($link->toModule, $names, true)) {
                $violations[] = new Violation($link, "{$subject->name} may not listen to events of {$link->toModule}.");
            }
        }

        if ($violations === []) {
            return Result::passed();
        }

        $list = implode(', ', $names);

        return new Result($violations, fn (array $new): string => Report::violations("Module {$subject->name} ignores the events of {$list}.", $new));
    }

    /**
     * @param  list<list<string>>  $cycles
     * @return list<string>|null the cycle that holds both ends of the link
     */
    private static function cycleOf(array $cycles, ModuleLink $link): ?array
    {
        foreach ($cycles as $cycle) {
            if (in_array($link->fromModule, $cycle, true) && in_array($link->toModule, $cycle, true)) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $modules
     * @return list<ModuleLink>
     */
    private function linksFrom(array $modules): array
    {
        return array_values(array_filter(
            $this->larch->links($this->kinds),
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
