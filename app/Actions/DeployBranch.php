<?php

namespace App\Actions;

use App\Support\InitialDeployment;
use App\Support\Server;

class DeployBranch
{
    public function __invoke(Server $server, string $repository, string $branch)
    {
        $stage = match ($branch) {
            'main' => 'dev',
            'staging' => 'staging',
        };

        // foo-bar.dev.rockero.cz
        $domain = "{$repository}.{$stage}." . config('services.forge.domain');

        if ($site = $server->site($domain)) {
            $result = $site->deploySite();

            abort_if(is_null($result), 500);

            return;
        }

        // dev_foo_bar
        $database = $stage . '_' . str_replace('-', '_', $repository);

        $initialDeployment = new InitialDeployment($domain, $database, $repository, $branch);

        $initialDeployment->script([
            'git reset --hard && git clean -df',
            "git pull origin {$branch}",
            '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
            '$FORGE_PHP artisan migrate --force',
            '$FORGE_PHP artisan queue:restart',
        ]);

        $initialDeployment->run();
    }
}
