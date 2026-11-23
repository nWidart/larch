<?php

namespace Fixtures\References;

use Fixtures\Other\Attribute;
use Fixtures\Other\Base;
use Fixtures\Other\Contract;
use Fixtures\Other\Thrown;
use Fixtures\Other\Trait_;
use Fixtures\Other\Unused;

#[Attribute]
final class References extends Base implements Contract
{
    use Trait_;

    public function run(\Fixtures\Other\Param $param): \Fixtures\Other\Returned
    {
        new \Fixtures\Other\Created;
        \Fixtures\Other\Facade::call();
        $value = \Fixtures\Other\Enum::Case;
        $check = $param instanceof \Fixtures\Other\Checked;
        $name = \Fixtures\Other\Named::class;
        \Fixtures\Other\helper();
        $constant = \Fixtures\Other\CONSTANT;
        $self = new self;
        $static = static::class;

        try {
        } catch (Thrown $e) {
        }

        $anonymous = new class {
            public function inner(): \Fixtures\Other\InsideAnonymous {}
        };

        return parent::run($param);
    }
}

interface Second extends \Fixtures\Other\ParentInterface {}
