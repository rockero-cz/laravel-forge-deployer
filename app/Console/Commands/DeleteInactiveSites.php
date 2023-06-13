<?php

namespace App\Console\Commands;

use App\Support\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeleteInactiveSites extends Command
{
    protected $signature = 'app:delete-inactive-sites';
    protected $description = 'Delete all inactive sites.';

    /**
     * Execute the console command.
     */
    public function handle(Server $server): int
    {
        foreach ($server->sites() as $site) {
            $splittedName = explode('.', $site->name);

            // The deployment is for the pull request
            if (is_numeric($splittedName[0])) {
                /** @var array<int, array> */
                $pullRequests = Http::github()->get('repos/' . $site->repository . '/pulls')->json();

                $matchingPullRequest = collect($pullRequests)->filter(function ($value) use ($splittedName) {
                    return $value['id'] === $splittedName[0];
                })->first();

                if (empty($pullRequests) || ! $matchingPullRequest || $matchingPullRequest['state'] === 'closed') {
                    $this->info('Deleting site ' . $site->name);

                    $site->delete();
                    $server->database($splittedName[0])?->delete();
                }
            }
            // The deploy is for the branch
            else {
                $date = $server->lastDeploymentAt($site);

                $stage = match ($site->repositoryBranch) {
                    'main' => 'dev',
                    'staging' => 'staging',
                    default => null
                };

                $databaseName = $stage . '_' . str_replace('-', '_', Str::after($site->repository, '/'));

                if (isset($date) && now()->diffInDays($date) >= 60) {
                    $this->info('Deleting site ' . $site->name);

                    $site->delete();

                    if (! $stage) {
                        continue;
                    }

                    $server->database($databaseName)?->delete();
                }
            }
        }

        return Command::SUCCESS;
    }
}
