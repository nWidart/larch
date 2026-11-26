<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\InvoiceController;

Route::get('audit/invoices', [InvoiceController::class, 'index']);
