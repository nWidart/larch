<?php

use Nwidart\Larch\Larch;

/*
 * Run by tests/Feature/MortiseTest.php in a separate Pest process, with the
 * app folder in LARCH_APP.
 */

beforeEach(fn () => Larch::reset()
    ->root((string) getenv('LARCH_APP'))
    ->paths('Modules')
    ->modules('Modules\*')
    ->baseline(sys_get_temp_dir().'/larch-preset-none.json'));

arch()->preset()->mortise();
