<?php

namespace Wired\Shared\Facades;

use Illuminate\Support\Facades\Facade;

final class Stock extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'stock';
    }
}
