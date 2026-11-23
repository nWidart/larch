<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Nwidart\Larch\Subjects\Module;
use Nwidart\Larch\Subjects\Modules;

if (! function_exists('Nwidart\Larch\larch')) {
    /**
     * The Larch configuration of this test run.
     */
    function larch(): Larch
    {
        return Larch::instance();
    }
}

if (! function_exists('Nwidart\Larch\module')) {
    /**
     * One module, by name.
     */
    function module(string $name): Module
    {
        return new Module($name);
    }
}

if (! function_exists('Nwidart\Larch\modules')) {
    /**
     * Every module of the app.
     */
    function modules(): Modules
    {
        return new Modules;
    }
}
