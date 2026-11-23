<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

afterEach(fn () => forgetApp($this));

/**
 * Run larch:graph in the wired fixture app.
 *
 * @param  array<string, mixed>  $options
 * @return array{exit: int, output: string}
 */
function larchGraph(array $options): array
{
    $app = require __DIR__.'/../Fixtures/wired/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    $exit = $kernel->call('larch:graph', ['--modules' => 'Wired\*', '--paths' => ['Modules'], ...$options]);

    return ['exit' => $exit, 'output' => $kernel->output()];
}

it('prints the links between modules as JSON', function () {
    $run = larchGraph(['--json' => true]);
    $graph = json_decode($run['output'], true);

    expect($run['exit'])->toBe(0)
        ->and($graph['version'])->toBe(1)
        ->and($graph['kinds'])->toBe(['import', 'facade', 'container', 'event', 'job'])
        ->and(array_column($graph['modules'], 'name'))->toBe(['Billing', 'Catalog', 'Inventory', 'Shared', 'Shipping'])
        ->and(array_values(array_filter($graph['links'], fn (array $link): bool => $link['kind'] === 'facade')))->toBe([[
            'from' => 'Wired\Billing\Actions\ChargeOrder',
            'to' => 'Wired\Inventory\Internal\StockLedger',
            'fromModule' => 'Billing',
            'toModule' => 'Inventory',
            'kind' => 'facade',
            'public' => false,
            'places' => [['file' => 'Modules/Billing/Actions/ChargeOrder.php', 'line' => 18]],
            'through' => 'the facade Wired\Shared\Facades\Stock',
            'boundIn' => 'Modules/Inventory/Providers/InventoryServiceProvider.php:13',
        ]])
        ->and(array_column($graph['unknown'], 'line'))->toBe([19, 20]);
});

it('reads the code only with --imports-only', function () {
    $graph = json_decode(larchGraph(['--imports-only' => true])['output'], true);

    expect($graph['kinds'])->toBe(['import'])
        ->and(array_unique(array_column($graph['links'], 'kind')))->toBe(['import'])
        ->and($graph['unknown'])->toBe([]);
});

it('writes the HTML report with the graph in it', function () {
    $file = sys_get_temp_dir().'/larch-graph-'.uniqid().'.html';

    try {
        $run = larchGraph(['--html' => true, '--output' => $file]);
        $html = (string) file_get_contents($file);
    } finally {
        @unlink($file);
    }

    expect($run['exit'])->toBe(0)
        ->and($run['output'])->toContain('The report is in')
        ->and($html)->toContain('<title>Larch module graph</title>')
        ->and($html)->not->toContain('/*LARCH_DATA*/')
        ->and($html)->toContain('"through":"the facade Wired\\\\Shared\\\\Facades\\\\Stock"');
});

it('says what is wrong when the modules are unknown', function () {
    $run = larchGraph(['--modules' => 'Wired', '--imports-only' => true]);

    expect($run['exit'])->toBe(1)
        ->and($run['output'])->toContain('larch()->modules() takes a pattern that ends in \\*');
});
