<?php

namespace App\Http\Controllers;

use App\Actions\DeployBranchAction;
use App\Actions\DeployPullRequestAction;
use App\Support\Server;

class DeployController extends Controller
{
    /**
     * Handle deployment of the given branch.
     */
    public function deployBranch(Server $server, string $repository, string $branch)
    {
        return app(DeployBranchAction::class)->run($server, $repository, $branch);
    }

    /**
     * Handle deployment of the given pull request.
     */
    public function deployPullRequest(Server $server, string $repository, string $number)
    {
        return app(DeployPullRequestAction::class)->run($server, $repository, $number);
    }
}
