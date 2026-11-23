<?php

declare(strict_types=1);

use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Mortise\MortiseConfig;
use Nwidart\Larch\Rules\Rules;
use Nwidart\Larch\Rules\StronglyConnected;
use Nwidart\Larch\Rules\Violation;

function mortiseApp(string $app): Larch
{
    return Larch::reset()
        ->root(__DIR__.'/../Fixtures/mortise/'.$app)
        ->paths('Modules')
        ->modules('Modules\*');
}

/**
 * What Larch finds in a shared fixture app, in the form of expected.json.
 *
 * @return array{no-circular-use: list<list<string>>|null, public-surface-only: list<array{from: string, to: string}>|null}
 */
function mortiseResult(string $app): array
{
    $larch = mortiseApp($app);
    $config = MortiseConfig::read($larch->projectRoot());
    $modules = array_keys($larch->moduleMap());
    $rules = new Rules($larch, [EdgeKind::Import], $config->folders);

    $cycles = null;

    if ($config->noCircularUse) {
        $adjacency = [];

        foreach ($rules->noCycles($modules)->breaches as $violation) {
            $adjacency[$violation->link->fromModule][$violation->link->toModule] = true;
        }

        $cycles = array_values(array_filter(StronglyConnected::components($adjacency), fn (array $cycle): bool => count($cycle) > 1));
    }

    $links = null;

    if ($config->publicSurfaceOnly) {
        $links = array_values(array_unique(array_map(
            fn (Violation $violation): array => ['from' => $violation->link->fromClass, 'to' => $violation->link->toClass],
            $rules->onlyUsePublicApis($modules)->breaches,
        ), SORT_REGULAR));
    }

    return ['no-circular-use' => $cycles, 'public-surface-only' => $links];
}

it('agrees with the expected result of each shared fixture app', function (string $app) {
    $expected = json_decode((string) file_get_contents(__DIR__."/../Fixtures/mortise/{$app}/expected.json"), true);

    expect(mortiseResult($app))->toBe($expected);
})->with(['defaults', 'custom-folders', 'all-off']);

it('runs the rules of mortise.json through the preset, in one test', function () {
    $run = runPest('tests/Fixtures/preset/MortiseTest.php', ['LARCH_APP' => realpath(__DIR__.'/../Fixtures/mortise/defaults')]);

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain(<<<'TXT'
              mortise.json, no-circular-use:
            Modules use each other in 1 cycle:

              Checkout, Shipping
                Checkout uses Shipping in 1 place:
                  Modules/Checkout/app/Actions/PlaceOrder.php:9  Modules\Checkout\Actions\PlaceOrder uses Modules\Shipping\Contracts\Ships
                Shipping uses Checkout in 1 place:
                  Modules/Shipping/app/Listeners/CreateShipment.php:9  Modules\Shipping\Listeners\CreateShipment uses Modules\Checkout\Events\OrderPaid

            mortise.json, public-surface-only:
            Modules use other modules only through their public API. 2 links break it:

              Modules/Billing/app/Actions/ChargeOrder.php:14
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
                It is internal to Catalog. The public API of a module is: Contracts, Events.

              Modules/Billing/app/Models/Invoice.php:9 (also line 11)
                Modules\Billing\Models\Invoice uses Modules\Shared\Money\Money
                It is internal to Shared. The public API of a module is: Contracts, Events.
            TXT);
});

it('passes the preset when the rules are off', function () {
    $run = runPest('tests/Fixtures/preset/MortiseTest.php', ['LARCH_APP' => realpath(__DIR__.'/../Fixtures/mortise/all-off')]);

    expect($run['exit'])->toBe(0)->and($run['output'])->toContain('1 passed');
});

describe('mortise.json', function () {
    beforeEach(function () {
        $this->app = sys_get_temp_dir().'/larch-mortise-'.uniqid();
        mkdir($this->app);
    });

    afterEach(function () {
        if (is_file($this->app.'/mortise.json')) {
            unlink($this->app.'/mortise.json');
        }
        rmdir($this->app);
    });

    it('uses the defaults for a missing file, a missing key or a file without rules', function (?string $json) {
        if ($json !== null) {
            file_put_contents($this->app.'/mortise.json', $json);
        }

        expect(MortiseConfig::read($this->app))->toEqual(new MortiseConfig(true, true, ['Contracts', 'Events']));
    })->with([
        'no file' => null,
        'no rules' => '{"team": "shop"}',
        'empty rules' => '{"rules": {}}',
        'empty surface' => '{"rules": {"public-surface-only": {}}}',
    ]);

    it('reads each key', function () {
        file_put_contents($this->app.'/mortise.json', '{"rules": {"no-circular-use": false, "public-surface-only": {"enabled": false, "folders": ["Api"]}}}');

        expect(MortiseConfig::read($this->app))->toEqual(new MortiseConfig(false, false, ['Api']));
    });

    it('stops on a file that does not hold rules', function (string $json, string $why) {
        file_put_contents($this->app.'/mortise.json', $json);

        expect(fn () => MortiseConfig::read($this->app))->toThrow(LarchException::class, $why);
    })->with([
        ['{', 'is not valid JSON'],
        ['[1]', 'the file is not a JSON object'],
        ['{"rules": true}', '"rules" is not an object'],
        ['{"rules": {"no-circular-use": "yes"}}', '"no-circular-use" is not true or false'],
        ['{"rules": {"public-surface-only": true}}', '"public-surface-only" is not an object'],
        ['{"rules": {"public-surface-only": {"folders": "Contracts"}}}', '"public-surface-only.folders" is not a list of folder names'],
    ]);
});
