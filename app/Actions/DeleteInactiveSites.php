<?php

namespace App\Actions;

use App\Support\Server;
use Illuminate\Support\Facades\Http;

class DeleteInactiveSites
{
    public function __invoke(Server $server)
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

                if (empty($pullRequests) || !$matchingPullRequest || $matchingPullRequest->state === 'closed') {
                    $delete->push($site->name);
                }
            }
            // The deploy is for the branch
            else {
                $date = $server->lastDeploymentAt($site);

                if (isset($date) && now()->diffInDays($date) >= 30) {
                    $delete->push($site->name);
                }
            }
        }

        dd($delete);
    }
}
