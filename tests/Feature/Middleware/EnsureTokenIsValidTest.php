<?php

use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('project.deployer.token', 'fake-token');

    Route::get('testing-route', fn () => '')->middleware(EnsureTokenIsValid::class);
});

it('returns unauthorized response status', function () {
    $response = $this->get('testing-route');

    expect($response->status())->toBe(403);
});

it('returns success response status', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer fake-token'])->get('testing-route');

    expect($response->status())->toBe(200);
});
