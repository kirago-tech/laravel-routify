<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/extra', fn () => 'extra')->name('extra.show');
