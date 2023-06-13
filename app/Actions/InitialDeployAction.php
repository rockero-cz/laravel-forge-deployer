<?php

namespace App\Actions;

use App\Support\InitialDeployment;
use Laravel\Forge\Resources\Site;

class InitialDeployAction
{
    /**
     * Run the action.
     */
    public function run(string $url, string $database, string $repository, string $branch): ?Site
    {
        $initialDeployment = new InitialDeployment($url, $database, $repository, $branch);

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
