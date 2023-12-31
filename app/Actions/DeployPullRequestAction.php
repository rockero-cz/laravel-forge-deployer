<?php

namespace App\Actions;

use App\Support\InitialDeployment;
use App\Support\Server;
use Illuminate\Support\Facades\Http;
use Laravel\Forge\Resources\Site;

class DeployPullRequestAction
{
    /**
     * Run the action.
     */
    public function run(Server $server, string $repository, string|int $number): ?Site
    {
        $pullRequest = Http::github()->get('repos/' . config('services.github.owner') . '/' . $repository . '/pulls/' . $number)->json();
        $pullRequestId = $pullRequest['id'];
        $url = "{$repository}-{$pullRequestId}.dev." . config('services.forge.domain');

        if ($site = $server->site($url)) {
            return $site->deploySite(false);
        }

        $initialDeployment = new InitialDeployment($url, $pullRequestId, $repository, $pullRequest['base']['ref']);
        $site = $this->initialDeploy($initialDeployment, $number);

        // Post comment with domain on the github PR
        Http::github()->post('repos/' . config('services.github.owner') . "/{$repository}/issues/{$number}/comments", ['body' => "https://{$url}"]);

        return $site;
    }

    /**
     * Handle initial deployment.
     */
    private function initialDeploy(InitialDeployment $initialDeployment, string|int $number): ?Site
    {
        $initialDeployment->script([
            "git fetch origin refs/pull/{$number}/merge",
            'git checkout FETCH_HEAD',
            '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
            '$FORGE_PHP artisan migrate --force',
            '$FORGE_PHP artisan queue:restart',
        ]);

        return $initialDeployment->run();
    }
}
