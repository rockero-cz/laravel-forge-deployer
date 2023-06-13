<?php

namespace App\Actions;

use App\Support\Server;
use Illuminate\Http\Request;

class DeployApplication
{
    public function __invoke(Server $server, Request $request)
    {
        // $request->validate([
        //     'context' => 'required|array',
        //     'context.event_name' => 'required',
        //     'context.ref_name' => 'required',
        //     'context.event' => 'required',
        // ]);

        // $context = (array) $request->input('context');

        $context = [
            'event_name' => 'push',
            'ref_name' => 'main',
            'event' => [
                'repository' => [
                    'name' => 'forge-test',
                ],
            ],
        ];

        $event = $context['event_name'];
        $repository = $context['event']['repository']['name'];

        $sharedCommands = [
            '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
            '$FORGE_PHP artisan migrate --force',
            '$FORGE_PHP artisan queue:restart',
        ];

        if ($event === 'pull_request') {
            $pullRequestNumber = $context['event']['pull_request']['number'];

            (new DeployPullRequest)->__invoke($server, $repository, $pullRequestNumber);
        } else {
            $branch = $context['ref_name'];

            (new DeployBranch)->__invoke($server, $repository, $branch);
        }
    }
}
