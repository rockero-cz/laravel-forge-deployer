<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use App\Support\Server;
use App\Support\InitialDeployment;

class DeployPullRequest
{
    public function __invoke(Server $server, string $repository, string|int $number)
    {
        $pullRequest = Http::github()->get('repos/rockero-cz/'. $repository .'/pulls/' . $number)->json();

        $domain = $pullRequest['id'] . '.dev.' . config('services.forge.domain');

        if ($site = $server->site($domain)) {
            $result = $site->deploySite();

            abort_if(is_null($result), 500);

            return;
        }

        $initialDeployment = new InitialDeployment($domain, $pullRequest['id'], $repository, $pullRequest['base']['ref']);

        $initialDeployment->script([
            "git fetch origin refs/pull/{$number}/merge",
            'git checkout FETCH_HEAD',
            '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
            '$FORGE_PHP artisan migrate --force',
            '$FORGE_PHP artisan queue:restart',
        ]);

        $initialDeployment->run();

        // Post comment with domain on the github PR
        Http::github()->post("repos/rockero-cz/{$repository}/issues/{$number}/comments", ['body' => "https://{$domain}"]);
    }
}
