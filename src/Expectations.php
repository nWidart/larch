<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Baseline\Baseline;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Mortise\MortisePreset;
use Nwidart\Larch\Rules\AppRules;
use Nwidart\Larch\Rules\Breach;
use Nwidart\Larch\Rules\Result;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Subjects\Jobs;
use Nwidart\Larch\Subjects\Listeners;
use Nwidart\Larch\Subjects\Models;
use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\Modules;
use Nwidart\Larch\Subjects\MortiseRules;
use Nwidart\Larch\Subjects\PublicApi;
use Nwidart\Larch\Subjects\Routes;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * The bodies of the Pest expectations registered in Autoload.php.
 *
 * @internal
 */
final class Expectations
{
    /**
     * @param  Expectation<mixed>  $expectation
     * @param  list<mixed>  $targets
     */
    public static function toOnlyDependOn(Expectation $expectation, array $targets): PendingRule
    {
        $subject = $expectation->value;

        if (! $subject instanceof Module) {
            throw self::wrongSubject('toOnlyDependOn', 'module(\'Name\')', $subject);
        }

        foreach ($targets as $target) {
            if (! $target instanceof Module && ! $target instanceof PublicApi) {
                throw new LarchException('toOnlyDependOn() takes module(\'Name\') or module(\'Name\')->publicApi(), got '.get_debug_type($target).'.');
            }
        }

        /** @var list<Module|PublicApi> $targets */
        return new PendingRule($expectation, fn (array $kinds) => self::assert(
            self::rules($kinds)->onlyDependOn($subject, $targets),
        ));
    }

    /**
     * @param  Expectation<mixed>  $expectation
     */
    public static function toOnlyUsePublicApis(Expectation $expectation): PendingRule
    {
        $modules = self::moduleNames('toOnlyUsePublicApis', $expectation->value);

        return new PendingRule($expectation, fn (array $kinds) => self::assert(
            self::rules($kinds)->onlyUsePublicApis($modules),
        ));
    }

    /**
     * @param  Expectation<mixed>  $expectation
     */
    public static function toHaveNoCycles(Expectation $expectation): PendingRule
    {
        $modules = self::moduleNames('toHaveNoCycles', $expectation->value);

        return new PendingRule($expectation, fn (array $kinds) => self::assert(
            self::rules($kinds)->noCycles($modules),
        ));
    }

    /**
     * @param  list<mixed>  $targets
     */
    public static function toIgnoreEventsOf(mixed $subject, array $targets): void
    {
        if (! $subject instanceof Module) {
            throw self::wrongSubject('toIgnoreEventsOf', 'module(\'Name\')', $subject);
        }

        if ($targets === []) {
            throw new LarchException('toIgnoreEventsOf() takes at least one module(\'Name\').');
        }

        foreach ($targets as $target) {
            if (! $target instanceof Module) {
                throw new LarchException('toIgnoreEventsOf() takes module(\'Name\'), got '.get_debug_type($target).'.');
            }
        }

        /** @var list<Module> $targets */
        self::assert(self::rules([EdgeKind::Event])->ignoreEventsOf($subject, $targets));
    }

    /**
     * @param  list<mixed>  $middleware
     */
    public static function toUseMiddleware(mixed $subject, array $middleware): void
    {
        if (! $subject instanceof Routes) {
            throw self::wrongSubject('toUseMiddleware', 'larch()->routes()', $subject);
        }

        self::assert(self::appRules()->useMiddleware($subject, self::names('toUseMiddleware', 'a middleware', $middleware)));
    }

    public static function toHavePolicy(mixed $subject): void
    {
        if (! $subject instanceof Models) {
            throw self::wrongSubject('toHavePolicy', 'larch()->models()', $subject);
        }

        self::assert(self::appRules()->havePolicy($subject));
    }

    /**
     * @param  list<mixed>  $members
     */
    public static function toDefine(mixed $subject, array $members): void
    {
        if (! $subject instanceof Jobs) {
            throw self::wrongSubject('toDefine', 'larch()->jobs()', $subject);
        }

        self::assert(self::appRules()->define($subject, self::names('toDefine', 'a property or method name', $members)));
    }

    public static function toBeQueued(mixed $subject): void
    {
        if (! $subject instanceof Listeners) {
            throw self::wrongSubject('toBeQueued', 'larch()->listenersOf(...)', $subject);
        }

        self::assert(self::appRules()->beQueued($subject));
    }

    public static function toBeInMorphMap(mixed $subject): void
    {
        if (! $subject instanceof Models) {
            throw self::wrongSubject('toBeInMorphMap', 'larch()->models()', $subject);
        }

        self::assert(self::appRules()->beInMorphMap($subject));
    }

    /**
     * The rules of mortise.json, checked on imports, as Mortise checks them.
     */
    public static function toFollowMortiseRules(mixed $subject): void
    {
        if (! $subject instanceof MortiseRules) {
            throw self::wrongSubject('toFollowMortiseRules', 'the mortise preset', $subject);
        }

        self::assertRules((new MortisePreset(Larch::instance()))->results(), 'mortise.json, ');
    }

    /**
     * @param  list<mixed>  $names
     * @return list<string>
     */
    private static function names(string $expectation, string $what, array $names): array
    {
        if ($names === []) {
            throw new LarchException("{$expectation}() takes at least one name.");
        }

        $valid = [];

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                throw new LarchException("{$expectation}() takes {$what}, got ".get_debug_type($name).'.');
            }

            $valid[] = $name;
        }

        return $valid;
    }

    private static function appRules(): AppRules
    {
        return new AppRules(Larch::instance());
    }

    /**
     * @param  list<EdgeKind>  $kinds
     */
    private static function rules(array $kinds): Rules
    {
        return new Rules(Larch::instance(), $kinds);
    }

    /**
     * @return list<string>
     */
    private static function moduleNames(string $expectation, mixed $subject): array
    {
        if ($subject instanceof Modules) {
            return array_keys(Larch::instance()->moduleMap());
        }

        if ($subject instanceof Module) {
            Larch::instance()->namespaceOf($subject->name);

            return [$subject->name];
        }

        throw self::wrongSubject($expectation, 'module(\'Name\') or modules()', $subject);
    }

    /**
     * Fail on the violations the baseline does not accept.
     *
     * @template T of Breach
     *
     * @param  Result<T>  $result
     */
    private static function assert(Result $result): void
    {
        self::assertRules(['' => $result]);
    }

    /**
     * Fail on the violations the baseline does not accept, for several rules
     * in one test. Each rule has its own entries in the baseline, and its own
     * part of the message, under its name.
     *
     * @template T of Breach
     *
     * @param  array<string, Result<T>>  $results  rule => result; '' for a test of one rule
     */
    private static function assertRules(array $results, string $title = ''): void
    {
        $larch = Larch::instance();
        $parts = [];
        $accepted = 0;

        foreach ($results as $rule => $result) {
            $new = Baseline::check($larch->baselinePath(), $result->breaches, $rule === '' ? null : $rule);
            $accepted += count($result->breaches) - count($new);

            if ($new !== []) {
                $parts[] = ($rule === '' ? '' : "{$title}{$rule}:\n").$result->report($new);
            }
        }

        if ($parts === []) {
            expect(true)->toBeTrue();

            return;
        }

        $message = implode("\n\n", $parts);

        if ($accepted > 0) {
            $message .= "\n\n".$accepted.' more '.($accepted === 1 ? 'violation is' : 'violations are').' in the baseline, '.basename($larch->baselinePath()).'.';
        }

        throw new ExpectationFailedException($message);
    }

    private static function wrongSubject(string $expectation, string $expected, mixed $subject): LarchException
    {
        return new LarchException("{$expectation}() expects {$expected} as its subject, got ".get_debug_type($subject).'.');
    }
}
