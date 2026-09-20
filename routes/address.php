<?php

declare(strict_types=1);

use Chikolokoy08\PhToolkit\Laravel\Http\AddressController;
use Illuminate\Support\Facades\Route;

Route::get('regions', [AddressController::class, 'regions'])->name('regions');
Route::get('provinces', [AddressController::class, 'provinces'])->name('provinces');
Route::get('cities', [AddressController::class, 'cities'])->name('cities');
Route::get('barangays', [AddressController::class, 'barangays'])->name('barangays');
