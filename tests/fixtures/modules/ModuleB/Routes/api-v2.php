<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/v2/orders', fn () => 'v2')->name('orders.v2.index');
