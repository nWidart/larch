<?php

use Fixtures\Other\Controller;
use Fixtures\Other\NeverUsed;

\Fixtures\Other\Router::get('/', [Controller::class, 'index']);

return new class extends \Fixtures\Other\Migration
{
    public function up(\Fixtures\Other\Blueprint $table): void {}
};
