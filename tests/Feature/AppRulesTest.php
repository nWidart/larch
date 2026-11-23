<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Nwidart\Larch\Exceptions\LarchException;

use function Nwidart\Larch\larch;

beforeEach(fn () => wired());

afterEach(fn () => forgetApp($this));

describe('toUseMiddleware', function () {
    it('lists the routes without the middleware', function () {
        $message = failureOf(fn () => expect(larch()->routes()->prefixed('api')->except('api/health'))->toUseMiddleware('auth:sanctum'));

        expect($message)->toBe(<<<'TXT'
            Routes under api, except api/health, use the middleware auth:sanctum. 2 routes break it:

              POST api/refunds
                Wired\Billing\Http\RefundController@store
                Middleware: api

              GET api/webhooks/stripe
                Closure
                Middleware: api, admin
            TXT);
    });

    it('passes when every route runs the middleware', function () {
        expect(larch()->routes()->prefixed('api')->except('api/webhooks/*', 'api/health', 'refunds.store'))
            ->toUseMiddleware('auth:sanctum');
    });

    it('takes a group, a class and routes by name', function () {
        expect(larch()->routes()->prefixed('api'))->toUseMiddleware('api')
            ->and(larch()->routes()->named('refunds.*'))->toUseMiddleware('api')
            ->and(larch()->routes()->named('refunds.show'))->toUseMiddleware(Authenticate::class);
    });

    it('fails for a route outside the group', function () {
        expect(failureOf(fn () => expect(larch()->routes()->except('storage/*'))->toUseMiddleware('api')))
            ->toBe(<<<'TXT'
                Routes, except storage/*, use the middleware api. 1 route breaks it:

                  GET home
                    Closure
                    Middleware: web
                TXT);
    });

    it('refuses a subject that is not routes', function () {
        expect('api')->toUseMiddleware('auth');
    })->throws(LarchException::class, 'toUseMiddleware() expects larch()->routes() as its subject, got string.');
});

describe('toHavePolicy', function () {
    it('lists the models without a policy', function () {
        $message = failureOf(fn () => expect(larch()->models())->toHavePolicy());

        expect($message)->toBe(<<<'TXT'
            Models have a policy. 4 models break it:

              Modules/Billing/Models/CreditNote.php:7
                Wired\Billing\Models\CreditNote has no policy. Register one with Gate::policy(), or add a policy Laravel finds by name.

              Modules/Billing/Models/InvoiceLine.php:7
                Wired\Billing\Models\InvoiceLine has no policy. Register one with Gate::policy(), or add a policy Laravel finds by name.

              Modules/Catalog/Models/Tag.php:7
                Wired\Catalog\Models\Tag has no policy. Register one with Gate::policy(), or add a policy Laravel finds by name.

              Modules/Shared/Models/Comment.php:7
                Wired\Shared\Models\Comment has no policy. Register one with Gate::policy(), or add a policy Laravel finds by name.
            TXT);
    });

    it('skips the models given to except()', function () {
        expect(larch()->models()->except(Pivot::class, 'Wired\Catalog\*', 'Wired\Shared\*', 'Wired\Billing\Models\CreditNote'))
            ->toHavePolicy();
    });
});

describe('toDefine', function () {
    it('lists the jobs that miss a member', function () {
        $message = failureOf(fn () => expect(larch()->jobs()->queued())->toDefine('tries', 'timeout'));

        expect($message)->toBe(<<<'TXT'
            Queued jobs define tries, timeout. 1 job breaks it:

              Modules/Shipping/Jobs/SendLabel.php:8
                Wired\Shipping\Jobs\SendLabel does not define tries, timeout.
            TXT);
    });

    it('checks jobs that are not queued too, without queued()', function () {
        expect(failureOf(fn () => expect(larch()->jobs())->toDefine('handle', 'tries')))
            ->toStartWith('Jobs define handle, tries. 2 jobs break it:')
            ->toContain('Wired\Billing\Jobs\RecalculateTotals does not define tries.');
    });
});

describe('toBeQueued', function () {
    it('lists the listeners that are not queued', function () {
        $message = failureOf(fn () => expect(larch()->listenersOf('Wired\Billing\Events\*'))->toBeQueued());

        expect($message)->toBe(<<<'TXT'
            Listeners of Wired\Billing\Events\* are queued. 1 listener breaks it:

              Modules/Inventory/Listeners/ReleaseHold.php:6
                Wired\Inventory\Listeners\ReleaseHold listens to Wired\Billing\Events\InvoiceIssued and is not queued. Implement ShouldQueue.
            TXT);
    });

    it('passes when no listener matches', function () {
        expect(larch()->listenersOf('Wired\Catalog\Events\*'))->toBeQueued();
    });
});

describe('toBeInMorphMap', function () {
    it('lists the models a morph relation stores by class name', function () {
        $message = failureOf(fn () => expect(larch()->models()->usedInMorphs())->toBeInMorphMap());

        expect($message)->toBe(<<<'TXT'
            Models used in morph relations are in the morph map. 1 model breaks it:

              Modules/Catalog/Models/Product.php:8
                Wired\Catalog\Models\Product has no alias in the morph map. Add it to Relation::morphMap() or Relation::enforceMorphMap().
            TXT);
    });
});
