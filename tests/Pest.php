<?php

declare(strict_types=1);

use Nwidart\Larch\Larch;

/**
 * Configure Larch for the shop fixture: five modules under Modules\.
 */
function shop(): Larch
{
    return Larch::reset()
        ->root(__DIR__.'/Fixtures/shop')
        ->paths('Modules')
        ->modules('Modules\*');
}
