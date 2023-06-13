<?php

namespace App\Console\Commands;

use App\Support\Server;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeleteInactiveSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-inactive-sites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all inactive sites.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Server $server)
    {
        $sites = $server->sites();
        $delete = collect([]);

        if (!$sites) {
            return;
        }

        foreach ($sites as $site) {
            $splittedName = explode('.', $site->name);

            // The deployment is for the pull request
            if (is_numeric($splittedName[0])) {
                $pullRequests = Http::github()->get('repos/'.$site->repository.'/pulls')->json();

                $matchingPullRequest = collect($pullRequests)->filter(function ($value) use ($splittedName) {
                    return $value['id'] === $splittedName[0];
                })->first();

                if (empty($pullRequests) || !$matchingPullRequest || $matchingPullRequest['state'] === 'closed') {
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

                    if (!$stage) {
                        continue;
                    }

                    $server->database($databaseName)?->delete();
                }
            }
        }
    }
}
