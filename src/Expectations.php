<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Baseline\Baseline;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Rules\Result;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\Modules;
use Nwidart\Larch\Subjects\PublicApi;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * The bodies of the Pest expectations registered in Autoload.php.
 *
 * @internal
 */
final class Expectations
{
    /**
     * @param  list<mixed>  $targets
     */
    public static function toOnlyDependOn(mixed $subject, array $targets): void
    {
        if (! $subject instanceof Module) {
            throw self::wrongSubject('toOnlyDependOn', 'module(\'Name\')', $subject);
        }

        foreach ($targets as $target) {
            if (! $target instanceof Module && ! $target instanceof PublicApi) {
                throw new LarchException('toOnlyDependOn() takes module(\'Name\') or module(\'Name\')->publicApi(), got '.get_debug_type($target).'.');
            }
        }

        /** @var list<Module|PublicApi> $targets */
        self::assert((new Rules(Larch::instance()))->onlyDependOn($subject, $targets));
    }

    public static function toOnlyUsePublicApis(mixed $subject): void
    {
        self::assert((new Rules(Larch::instance()))->onlyUsePublicApis(self::moduleNames('toOnlyUsePublicApis', $subject)));
    }

    public static function toHaveNoCycles(mixed $subject): void
    {
        self::assert((new Rules(Larch::instance()))->noCycles(self::moduleNames('toHaveNoCycles', $subject)));
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
