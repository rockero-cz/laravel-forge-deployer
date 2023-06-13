<?php

use App\Actions\DeletePullRequestDeployment;
use App\Actions\DeployBranch;
use App\Actions\DeployPullRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deploy:branch {repository} {branch}', function ($repository, $branch) {
    (new DeployBranch)($repository, $branch);

    $stage = match ($branch) {
        'main' => 'dev',
        'staging' => 'staging',
    };

    $domain = "{$repository}.{$stage}." . env('FORGE_DOMAIN');
    $this->info("Branch {$branch} deployed at https://{$domain}");
});

Artisan::command('deploy:pull {repository} {number}', function ($repository, $number) {
    (new DeployPullRequest)($repository, $number);

    $pullRequest = Http::github()->get('repos/rockero-cz/' . $repository . '/pulls/' . $number)->json();

    $domain = "{$pullRequest['id']}.dev." . env('FORGE_DOMAIN');
    $this->info("Pull request #{$number} deployed at https://{$domain}");
});

Artisan::command('delete:pull {repository} {number}', function ($repository, $number) {
    (new DeletePullRequestDeployment)($repository, $number);

    $this->info("Pull request #{$number} deployment deleted");
});
