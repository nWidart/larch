<?php

namespace Modules\Shared\Money;

final class Money
{
    public static function of(int $amount): self
    {
        return new self;
    }
}
