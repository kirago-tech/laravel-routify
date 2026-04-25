<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/orphan', fn () => 'orphan')->name('orphan.show');
