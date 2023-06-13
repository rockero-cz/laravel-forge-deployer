<?php

namespace App\Actions;

use App\Support\InitialDeployment;
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

        // foo-bar.dev.rockero.cz
        $url = "{$repository}.{$environment}." . config('services.forge.domain');

        if ($site = $server->site($url)) {
            return $site->deploySite();
        }

        // dev_foo_bar
        $database = $environment . '_' . str_replace('-', '_', $repository);

        return app(InitialDeployAction::class)->run($url, $database, $repository, $branch);
    }
}
