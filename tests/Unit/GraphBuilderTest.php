<?php

declare(strict_types=1);

use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\CallSite;
use Nwidart\Larch\Graph\Edge;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Graph\GraphBuilder;

function referencesOf(string $class): array
{
    $graph = (new GraphBuilder(__DIR__.'/../Fixtures', []))->build([__DIR__.'/../Fixtures/references']);

    return array_values(array_map(
        fn (Edge $edge): string => $edge->to,
        array_filter($graph->edges, fn (Edge $edge): bool => $edge->from === $class),
    ));
}

it('finds every way a class names another', function () {
    expect(referencesOf('Fixtures\References\References'))->toEqualCanonicalizing([
        'Fixtures\Other\Attribute',
        'Fixtures\Other\Base',
        'Fixtures\Other\Contract',
        'Fixtures\Other\Trait_',
        'Fixtures\Other\Param',
        'Fixtures\Other\Returned',
        'Fixtures\Other\Created',
        'Fixtures\Other\Facade',
        'Fixtures\Other\Enum',
        'Fixtures\Other\Checked',
        'Fixtures\Other\Named',
        'Fixtures\Other\Thrown',
        'Fixtures\Other\InsideAnonymous',
    ]);
});

it('skips functions, constants, self, static, parent and imports that are not used', function () {
    expect(referencesOf('Fixtures\References\References'))
        ->not->toContain('Fixtures\Other\helper')
        ->not->toContain('Fixtures\Other\CONSTANT')
        ->not->toContain('Fixtures\Other\Unused')
        ->not->toContain('Fixtures\References\References');
});

it('records each declared class with its file, and each link with its line', function () {
    $graph = (new GraphBuilder(__DIR__.'/../Fixtures', []))->build([__DIR__.'/../Fixtures/references']);

    expect($graph->classes)->toBe([
        'Fixtures\References\Calls' => 'references/Calls.php',
        'Fixtures\References\References' => 'references/References.php',
        'Fixtures\References\Second' => 'references/References.php',
    ]);

    $created = array_values(array_filter($graph->edges, fn (Edge $edge): bool => $edge->to === 'Fixtures\Other\Created'))[0];

    expect($created->line)->toBe(19)
        ->and($created->kind)->toBe(EdgeKind::Import)
        ->and($created->file)->toBe('references/References.php');
});

it('links an interface to the interface it extends', function () {
    expect(referencesOf('Fixtures\References\Second'))->toBe(['Fixtures\Other\ParentInterface']);
});

it('skips excluded folders and code outside classes', function () {
    $graph = (new GraphBuilder(__DIR__.'/../Fixtures/shop', ['tests']))->build([__DIR__.'/../Fixtures/shop/Modules']);

    expect($graph->has('Modules\Billing\Tests\ChargeOrderTest'))->toBeFalse()
        ->and(array_filter($graph->edges, fn (Edge $edge): bool => $edge->to === 'Modules\Shipping\Listeners\CreateShipment'))->toBe([]);
});

it('stops with the file and the parser error when a file cannot be parsed', function () {
    $folder = sys_get_temp_dir().'/larch-broken-'.uniqid();
    mkdir($folder);
    file_put_contents($folder.'/Broken.php', '<?php class {');

    try {
        (new GraphBuilder($folder, []))->build([$folder]);
    } finally {
        unlink($folder.'/Broken.php');
        rmdir($folder);
    }
})->throws(LarchException::class, 'Larch could not parse Broken.php: Syntax error');

it('stops when a folder does not exist', function () {
    (new GraphBuilder('/tmp', []))->build(['/tmp/larch-does-not-exist']);
})->throws(LarchException::class, 'Larch could not find the folder larch-does-not-exist.');

it('collects the calls the runtime pass resolves, and where keys are bound', function () {
    $graph = (new GraphBuilder(__DIR__.'/../Fixtures', []))->build([__DIR__.'/../Fixtures/references/Calls.php']);

    $calls = array_map(
        fn (CallSite $call): string => "{$call->line} {$call->type} ".($call->target ?? 'null').' '.$call->expression,
        array_values(array_filter($graph->calls, fn (CallSite $call): bool => $call->type !== CallSite::STATIC || $call->target === 'Fixtures\Other\Job')),
    );

    expect($calls)->toBe([
        "13 container one app('one')",
        "14 container two resolve('two')",
        "15 container three app()->make('three')",
        "16 container four \$this->app->makeWith('four', [])",
        "17 container five \$app->make('five')",
        "18 container six App::make('six')",
        '19 container Fixtures\Other\Job app(Job::class)',
        '20 container null app($job)',
        '21 dispatch Fixtures\Other\Job dispatch(new Job)',
        '22 dispatch null dispatch_sync($job)',
        '23 dispatch Fixtures\Other\Job Bus::dispatch(new Job)',
        '24 static-dispatch Fixtures\Other\Job Job::dispatch()',
        '24 static Fixtures\Other\Job Job::dispatch()',
    ])->and($graph->bindings)->toBe(['bound' => 'references/Calls.php:25']);
});
