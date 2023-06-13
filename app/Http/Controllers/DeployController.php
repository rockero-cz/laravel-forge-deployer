<?php

namespace App\Http\Controllers;

use App\Actions\DeployBranch;
use App\Actions\DeployPullRequest;
use App\Support\Server;

class DeployController extends Controller
{
    /**
     * Handle deployment of the given branch.
     */
    public function deployBranch(Server $server, string $repository, string $branch)
    {
        return app(DeployBranch::class)->run($server, $repository, $branch);
    }

    /**
     * Handle deployment of the given pull request.
     */
    public function deployPullRequest(Server $server, string $repository, string $number)
    {
        return app(DeployPullRequest::class)->run($server, $repository, $number);
    }
}
