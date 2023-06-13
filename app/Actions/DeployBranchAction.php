<?php

namespace App\Actions;

use App\Support\Server;
use Laravel\Forge\Resources\Site;

class DeployBranchAction
{
    /**
     * Run the action.
     */
    public function run(Server $server, string $repository, string $branch): ?Site
    {
        $environment = match ($branch) {
            'main' => 'dev',
            'staging' => 'staging',
        };

        $url = "{$repository}.{$environment}." . config('services.forge.domain');
        $database = $environment . '_' . str_replace('-', '_', $repository);

        if ($site = $server->site($url)) {
            return $site->deploySite();
        }

        return app(InitialDeployAction::class)->run($url, $database, $repository, $branch);
    }
}
