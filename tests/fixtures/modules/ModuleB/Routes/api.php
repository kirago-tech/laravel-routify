<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::post('/orders', fn () => 'orders')->name('orders.store');
