<?php

namespace App\Http\Controllers;

use App\Actions\DeletePullRequestDeployment;

class WebhookController extends Controller
{
    public function __invoke()
    {
        if (request()->header('x-github-event') == 'pull_request') {
            $this->handlePullRequestEvent();
        }
    }

    private function handlePullRequestEvent()
    {
        if (request('action') === 'closed') {
            DeletePullRequestDeployment::run(request('repository.name'), request('pull_request.number'));
        }
    }
}
