<?php

use App\Http\Controllers\DeployController;
use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware(EnsureTokenIsValid::class)->group(function () {
    Route::post('deploy/{repository}/{branch}', [DeployController::class, 'deployBranch']);
    Route::post('deploy/{repository}/pull/{number}', [DeployController::class, 'deployPullRequest']);
    Route::post('deploy/{repository}/event/{event}', [DeployController::class, 'deployEvent']);
});
