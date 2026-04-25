<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/users', fn () => 'a-users')->name('users.index');
