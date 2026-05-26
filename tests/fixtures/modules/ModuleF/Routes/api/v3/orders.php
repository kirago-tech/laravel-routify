<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/orders-v3', fn () => 'orders-v3')->name('orders.v3.index');
