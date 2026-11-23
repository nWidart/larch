<?php

declare(strict_types=1);

/*
 * Runs tests/Fixtures/baseline/ModulesTest.php in a separate Pest process,
 * as a user would, with the baseline in a temporary file.
 */

beforeEach(function () {
    $this->baseline = sys_get_temp_dir().'/larch-baseline-'.uniqid().'.json';
});

afterEach(function () {
    @unlink($this->baseline);
});

/**
 * @return array{exit: int, output: string}
 */
function pestOnFixture(string $baseline, string ...$options): array
{
    $command = array_merge(
        [PHP_BINARY, 'vendor/bin/pest', 'tests/Fixtures/baseline/ModulesTest.php', '--colors=never'],
        $options,
    );

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), [
        'LARCH_BASELINE' => $baseline,
        'PATH' => (string) getenv('PATH'),
    ]);

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'output' => (string) $output];
}

/**
 * @param  list<array{string, string, string}>  $fingerprints  test, from, to
 */
function writeBaseline(string $path, array $fingerprints): void
{
    $tests = [];

    foreach ($fingerprints as [$test, $from, $to]) {
        $tests['Tests\Fixtures\baseline\ModulesTest > '.$test][] = ['from' => $from, 'to' => $to, 'kind' => 'import'];
    }

    file_put_contents($path, json_encode(['version' => 1, 'tests' => $tests]));
}

it('writes the violations of each test with --update-larch-baseline, and passes', function () {
    $run = pestOnFixture($this->baseline, '--update-larch-baseline');

    expect($run['exit'])->toBe(0)
        ->and($run['output'])->toContain('Larch: wrote 4 violations of 2 tests to the baseline')
        ->and(file_get_contents($this->baseline))->toBe(<<<'JSON'
            {
                "version": 1,
                "tests": {
                    "Tests\\Fixtures\\baseline\\ModulesTest > billing depends only on shared": [
                        {
                            "from": "Modules\\Billing\\Actions\\ChargeOrder",
                            "to": "Modules\\Catalog\\Contracts\\PricesOrders",
                            "kind": "import"
                        },
                        {
                            "from": "Modules\\Billing\\Actions\\ChargeOrder",
                            "to": "Modules\\Catalog\\Pricing\\PriceEngine",
                            "kind": "import"
                        }
                    ],
                    "Tests\\Fixtures\\baseline\\ModulesTest > no cycles": [
                        {
                            "from": "Modules\\Checkout\\Actions\\PlaceOrder",
                            "to": "Modules\\Shipping\\Contracts\\Ships",
                            "kind": "import"
                        },
                        {
                            "from": "Modules\\Shipping\\Listeners\\CreateShipment",
                            "to": "Modules\\Checkout\\Events\\OrderPaid",
                            "kind": "import"
                        }
                    ]
                }
            }

            JSON);
});

it('passes when every violation is in the baseline, and says how many are', function () {
    pestOnFixture($this->baseline, '--update-larch-baseline');

    $run = pestOnFixture($this->baseline);

    expect($run['exit'])->toBe(0)
        ->and($run['output'])->toContain('3 passed')
        ->and($run['output'])->toContain('Larch: 4 violations are in the baseline');
});

it('fails only on violations that are not in the baseline', function () {
    writeBaseline($this->baseline, [
        ['billing depends only on shared', 'Modules\Billing\Actions\ChargeOrder', 'Modules\Catalog\Contracts\PricesOrders'],
    ]);

    $run = pestOnFixture($this->baseline);

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain(<<<'TXT'
            Module Billing may only depend on Shared. 1 link breaks it:

              Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
                Billing may not depend on Catalog.

            1 more violation is in the baseline,
            TXT)
        ->and($run['output'])->not->toContain('Modules\Catalog\Contracts\PricesOrders');
});

it('reports a cycle with all its links when one link is new', function () {
    writeBaseline($this->baseline, [
        ['no cycles', 'Modules\Checkout\Actions\PlaceOrder', 'Modules\Shipping\Contracts\Ships'],
    ]);

    $run = pestOnFixture($this->baseline, '--filter=no cycles');

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain('Checkout uses Shipping in 1 place:')
        ->and($run['output'])->toContain('Shipping uses Checkout in 1 place:')
        ->and($run['output'])->toContain('1 more violation is in the baseline');
});

it('lists the entries that no violation matches', function () {
    pestOnFixture($this->baseline, '--update-larch-baseline');

    $data = json_decode((string) file_get_contents($this->baseline), true);
    $data['tests']['Tests\Fixtures\baseline\ModulesTest > catalog depends only on shared'] = [
        ['from' => 'Modules\Catalog\Models\Product', 'to' => 'Modules\Billing\Models\Invoice', 'kind' => 'import'],
    ];
    file_put_contents($this->baseline, json_encode($data));

    $run = pestOnFixture($this->baseline);

    expect($run['exit'])->toBe(0)
        ->and($run['output'])->toContain(<<<'TXT'
            Larch: 1 entry of the baseline
            TXT)
        ->and($run['output'])->toContain(<<<'TXT'
             no longer matches a violation. Run Pest with --update-larch-baseline to remove it:
                Tests\Fixtures\baseline\ModulesTest > catalog depends only on shared: Modules\Catalog\Models\Product > Modules\Billing\Models\Invoice (import)
            TXT);
});

it('keeps the entries of tests that did not run when it updates', function () {
    pestOnFixture($this->baseline, '--update-larch-baseline');
    writeBaseline($this->baseline, [
        ['billing depends only on shared', 'Modules\Billing\Actions\ChargeOrder', 'Modules\Catalog\Contracts\PricesOrders'],
        ['catalog depends only on shared', 'Modules\Catalog\Models\Product', 'Modules\Billing\Models\Invoice'],
    ]);

    pestOnFixture($this->baseline, '--update-larch-baseline', '--filter=billing');

    $tests = json_decode((string) file_get_contents($this->baseline), true)['tests'];

    expect(array_keys($tests))->toBe([
        'Tests\Fixtures\baseline\ModulesTest > billing depends only on shared',
        'Tests\Fixtures\baseline\ModulesTest > catalog depends only on shared',
    ])->and($tests['Tests\Fixtures\baseline\ModulesTest > billing depends only on shared'])->toHaveCount(2);
});

it('updates the baseline from parallel workers', function () {
    $run = pestOnFixture($this->baseline, '--parallel', '--processes=2', '--update-larch-baseline');

    expect($run['exit'])->toBe(0)
        ->and(array_keys(json_decode((string) file_get_contents($this->baseline), true)['tests']))->toBe([
            'Tests\Fixtures\baseline\ModulesTest > billing depends only on shared',
            'Tests\Fixtures\baseline\ModulesTest > no cycles',
        ])
        ->and(pestOnFixture($this->baseline, '--parallel', '--processes=2')['exit'])->toBe(0);
});

it('stops on a baseline of another version', function () {
    file_put_contents($this->baseline, '{"version": 2, "tests": {}}');

    expect(pestOnFixture($this->baseline)['output'])
        ->toContain('is not a Larch baseline of version 1. Delete it and run Pest with --update-larch-baseline.');
});

it('stops on a baseline that is not valid JSON', function () {
    file_put_contents($this->baseline, '{');

    $run = pestOnFixture($this->baseline);

    expect($run['exit'])->not->toBe(0)
        ->and($run['output'])->toContain('is not valid JSON: Syntax error. Delete it and run Pest with --update-larch-baseline.');
});
