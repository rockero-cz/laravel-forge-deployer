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
            default => 'dev'
        };

        $url = "{$repository}.{$environment}." . config('services.forge.domain');
        $database = $environment . '_' . str_replace('-', '_', $repository);

        if ($site = $server->site($url)) {
            return $site->deploySite(false);
        }

        $initialDeployment = new InitialDeployment($url, $database, $repository, $branch);

        return $this->initialDeploy($initialDeployment, $branch);
    }

    /**
     * Handle initial deployment.
     */
    private function initialDeploy(InitialDeployment $initialDeployment, string|int $branch): ?Site
    {
        $initialDeployment->script([
            'git reset --hard && git clean -df',
            "git pull origin {$branch}",
            '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
            '$FORGE_PHP artisan migrate --force',
            '$FORGE_PHP artisan queue:restart',
        ]);

        return $initialDeployment->run();
    }
}
