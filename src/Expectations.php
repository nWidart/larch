<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Baseline\Baseline;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Rules\Result;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\Modules;
use Nwidart\Larch\Subjects\PublicApi;
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
     */
    private static function assert(Result $result): void
    {
        $larch = Larch::instance();
        $new = Baseline::check($larch->baselinePath(), $result->violations);

        if ($new === []) {
            expect(true)->toBeTrue();

            return;
        }

        $message = $result->report($new);
        $accepted = count($result->violations) - count($new);

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
