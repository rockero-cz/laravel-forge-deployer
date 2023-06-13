<?php

namespace App\Providers;

use App\Support\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Forge\Forge;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Http::macro('github', fn () => $this
            ->withToken(config('services.github.token'))
            ->baseUrl('https://api.github.com')
        );

        $this->app->bind(Forge::class, function () {
            $forge = new Forge(config('services.forge.api_key'));

            $forge->setTimeout(360);

            return $forge;
        });

        $this->app->singleton(Server::class);
    }
}
