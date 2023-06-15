<?php

namespace App\Http\Controllers;

use App\Actions\DeployBranchAction;
use App\Actions\DeployPullRequestAction;
use App\Support\Server;
use Illuminate\Http\JsonResponse;

class DeployController extends Controller
{
    /**
     * Handle deployment of the given branch.
     */
    public function deployBranch(Server $server, string $repository, string $branch): JsonResponse
    {
        $site = app(DeployBranchAction::class)->run($server, $repository, $branch);

        if (! $site) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true, 'site' => $site]);
    }

    /**
     * Handle deployment of the given pull request.
     */
    public function deployPullRequest(Server $server, string $repository, string $number): JsonResponse
    {
        $site = app(DeployPullRequestAction::class)->run($server, $repository, $number);

        if (! $site) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true, 'site' => $site]);
    }

    /**
     * Handle deployment of the given event.
     */
    public function deployEvent(Server $server, string $repository, string $event): JsonResponse
    {
        $site = match ($event) {
            'pull_request' => app(DeployPullRequestAction::class)->run($server, $repository, request()->input('number')),
            default => app(DeployBranchAction::class)->run($server, $repository, request()->input('branch'))
        };

        if (! $site) {
            return response()->json(['success' => false], 409);
        }

        return response()->json(['success' => true, 'site' => $site]);
    }
}
