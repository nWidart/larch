<?php

declare(strict_types=1);

use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Larch;
use Nwidart\Larch\Mortise\MortiseConfig;
use Nwidart\Larch\Mortise\MortisePreset;
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
 * @return array{no-circular-use: list<list<string>>|null, public-surface-only: list<array{from: string, to: string}>|null, allowed-use: list<array{from: string, to: string, files: int}>|null}
 */
function mortiseResult(string $app): array
{
    $results = (new MortisePreset(mortiseApp($app)))->results();
    $cycles = null;

    if (isset($results['no-circular-use'])) {
        $adjacency = [];

        foreach ($results['no-circular-use']->breaches as $violation) {
            $adjacency[$violation->link->fromModule][$violation->link->toModule] = true;
        }

        $cycles = array_values(array_filter(StronglyConnected::components($adjacency), fn (array $cycle): bool => count($cycle) > 1));
    }

    $links = null;

    if (isset($results['public-surface-only'])) {
        $links = array_values(array_unique(array_map(
            fn (Violation $violation): array => ['from' => $violation->link->fromClass, 'to' => $violation->link->toClass],
            $results['public-surface-only']->breaches,
        ), SORT_REGULAR));
    }

    $uses = null;

    if (isset($results['allowed-use'])) {
        $files = [];

        foreach ($results['allowed-use']->breaches as $violation) {
            foreach ($violation->link->edges as $edge) {
                $files[$violation->link->fromModule][$violation->link->toModule][$edge->file] = true;
            }
        }

        $uses = [];

        foreach ($files as $from => $to) {
            foreach ($to as $module => $paths) {
                $uses[] = ['from' => $from, 'to' => $module, 'files' => count($paths)];
            }
        }

        usort($uses, fn (array $a, array $b): int => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);
    }

    return ['no-circular-use' => $cycles, 'public-surface-only' => $links, 'allowed-use' => $uses];
}

it('agrees with the expected result of each shared fixture app', function (string $app) {
    $expected = json_decode((string) file_get_contents(__DIR__."/../Fixtures/mortise/{$app}/expected.json"), true);

    expect(mortiseResult($app))->toBe($expected);
})->with(['defaults', 'custom-folders', 'all-off', 'module-off', 'allowed-use']);

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

it('reports the modules that break allowed-use, under its name', function () {
    $run = runPest('tests/Fixtures/preset/MortiseTest.php', ['LARCH_APP' => realpath(__DIR__.'/../Fixtures/mortise/allowed-use')]);

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain(<<<'TXT'
            mortise.json, allowed-use:
            Module Audit may only depend on no other module. 3 links break it:

              Modules/Audit/routes/web.php:6
                Modules/Audit/routes/web.php uses Modules\Billing\Http\Controllers\InvoiceController
                Audit may not depend on Billing.
            TXT);
});

it('keeps the baseline entries of each rule apart', function () {
    $baseline = sys_get_temp_dir().'/larch-preset-'.uniqid().'.json';

    try {
        runPest('tests/Fixtures/preset/MortiseTest.php', ['LARCH_APP' => realpath(__DIR__.'/../Fixtures/mortise/defaults'), 'LARCH_BASELINE' => $baseline], '--update-larch-baseline');

        expect(array_keys(json_decode((string) file_get_contents($baseline), true)['tests']))->toBe([
            'Tests\Fixtures\preset\MortiseTest > preset  → mortise  > no-circular-use',
            'Tests\Fixtures\preset\MortiseTest > preset  → mortise  > public-surface-only',
        ]);
    } finally {
        @unlink($baseline);
    }
});

it('reads no module when both rules are off', function () {
    expect((new MortisePreset(Larch::reset()->root(__DIR__.'/../Fixtures/mortise/all-off')))->results())->toBe([]);
});

it('passes the preset when the rules are off', function () {
    $run = runPest('tests/Fixtures/preset/MortiseTest.php', ['LARCH_APP' => realpath(__DIR__.'/../Fixtures/mortise/all-off')]);

    expect($run['exit'])->toBe(0)->and($run['output'])->toContain('1 passed');
});

describe('modules_statuses.json', function () {
    beforeEach(function () {
        $this->app = sys_get_temp_dir().'/larch-statuses-'.uniqid();
        mkdir($this->app);
    });

    afterEach(function () {
        if (is_file($this->app.'/modules_statuses.json')) {
            unlink($this->app.'/modules_statuses.json');
        }
        rmdir($this->app);
    });

    /**
     * Whether the preset finds the cycle of Checkout and Shipping in the
     * code of the app defaults, with the given statuses file or none.
     */
    function findsCycleWith(string $app, ?string $statuses): bool
    {
        if ($statuses !== null) {
            file_put_contents($app.'/modules_statuses.json', $statuses);
        }

        $larch = Larch::reset()->root($app)->paths((string) realpath(__DIR__.'/../Fixtures/mortise/defaults/Modules'))->modules('Modules\*');

        return (new MortisePreset($larch))->results()['no-circular-use']->breaches !== [];
    }

    it('matches the entry of a module as laravel-modules does', function (?string $statuses, bool $on) {
        expect(findsCycleWith($this->app, $statuses))->toBe($on);
    })->with([
        'another case' => ['{"checkout": true, "SHIPPING": true}', true],
        'the exact name before another case' => ['{"Checkout": true, "shipping": false, "Shipping": true}', true],
        'a value other than true' => ['{"Checkout": true, "Shipping": 1}', false],
        'no file' => [null, false],
        'an empty file' => ['', false],
        'an empty list' => ['[]', false],
    ]);

    it('stops on a file laravel-modules does not boot with', function (string $statuses) {
        expect(fn () => findsCycleWith($this->app, $statuses))->toThrow(LarchException::class, 'modules_statuses.json is not valid');
    })->with(['{', '[true]', '"on"']);
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
        'allowed use off' => '{"rules": {"allowed-use": {"enabled": false, "modules": {"Audit": []}}}}',
    ]);

    it('reads each key', function () {
        file_put_contents($this->app.'/mortise.json', '{"rules": {"no-circular-use": false, "public-surface-only": {"enabled": false, "folders": ["Api"]}, "allowed-use": {"modules": {"Audit": [], "History": ["Audit"]}}}}');

        expect(MortiseConfig::read($this->app))->toEqual(new MortiseConfig(false, false, ['Api'], ['Audit' => [], 'History' => ['Audit']]));
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
        ['[]', 'the file is not a JSON object'],
        ['{"rules": null}', '"rules" is not an object'],
        ['{"rules": {"no-circular-use": null}}', '"no-circular-use" is not true or false'],
        ['{"rules": {"public-surface-only": {"folders": {"0": "Pricing"}}}}', '"public-surface-only.folders" is not a list of folder names'],
        ['{"rules": {"allowed-use": [true]}}', '"allowed-use" is not an object'],
        ['{"rules": {"allowed-use": {"enabled": "yes"}}}', '"allowed-use.enabled" is not true or false'],
        ['{"rules": {"allowed-use": {"modules": ["Audit"]}}}', '"allowed-use.modules" is not an object'],
        ['{"rules": {"allowed-use": {"modules": {"Audit": "Billing"}}}}', '"allowed-use.modules.Audit" is not a list of module names'],
    ]);

    it('stops on a path it cannot read as a file', function () {
        mkdir($this->app.'/mortise.json');

        try {
            expect(fn () => MortiseConfig::read($this->app))->toThrow(LarchException::class, 'Larch could not read');
        } finally {
            rmdir($this->app.'/mortise.json');
        }
    });
});
