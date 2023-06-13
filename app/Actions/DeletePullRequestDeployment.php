<?php

namespace App\Actions;

use App\Support\Server;
use Illuminate\Support\Facades\Http;

class DeletePullRequestDeployment
{
    public function __invoke(string $repository, string|int $number)
    {
        $pullRequest = Http::github()->get('repos/' . config('services.github.owner') . '/' . $repository . '/pulls/' . $number)->json();

        $domain = $pullRequest['id'] . '.dev.' . config('services.forge.domain');

        $server = app(Server::class);
        $server->site($domain)?->delete();
        $server->database($pullRequest['id'])?->delete();
    }
    public static function run(...$arguments)
    {
        return (new self)(...$arguments);
    }
}
