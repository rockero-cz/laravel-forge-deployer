<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use Tests\TestCase;
use RuntimeException;

class PullRequestTest extends TestCase
{
    /** @test */
    public function deploys_pull_request_site()
    {
        // Pull Request ID: 123
        // Server ID: 987
        // Site ID: 876

        config()->set('services.forge.server_id', 987);
        config()->set('services.forge.domain', 'deploy.cz');
        config()->set('services.github.owner', 'rockero-cz');

        // Fake github response for PR details
        Http::fake([
            'https://api.github.com/repos/rockero-cz/foobar/pulls/1' => Http::response([
                'id' => 123,
                'base' => ['ref' => 'main'],
            ]),
        ]);

        // Setup fake returns
        $forge = $this->spy(Forge::class);

        $forge->shouldReceive('sites')
            ->andReturn([]);

        $forge->shouldReceive('createSite')
            ->andReturn(new Site(['id' => 876, 'serverId' => 987]));

        $forge->shouldReceive('siteEnvironmentFile')
            ->andReturn(implode("\n", [
                'APP_URL=',
                'DB_DATABASE=forge',
                'DB_USERNAME=root',
            ]));

        $forge->shouldReceive('get')
            ->with('servers/987/sites/876/deployment-history')
            ->andReturn([
                'deployments' => [
                    ['status' => 'finished'],
                ],
            ]);

        // Send request
        $this->withHeaders(['Authorization' => 'Bearer fake-token'])->post('deploy/foobar/pull/1')->assertOk();

        // Assert methods have been called
        $forge->shouldHaveReceived('createSite')
            ->with(987, [
                'domain' => '123.dev.deploy.cz',
                'project_type' => 'php',
                'directory' => '/123.dev.deploy.cz',
                'isolated' => false,
                'database' => '123',
                'php_version' => 'php81',
                'nginx_template' => 2972,
            ]);

        $forge->shouldHaveReceived('installGitRepositoryOnSite')
            ->with(987, 876, [
                'provider' => 'github',
                'repository' => 'rockero-cz/foobar',
                'branch' => 'main',
                'composer' => false,
            ]);

        $forge->shouldHaveReceived('updateSiteEnvironmentFile')
            ->with(987, 876, implode("\n", [
                'APP_URL=https://123.dev.deploy.cz',
                'DB_DATABASE=123',
                'DB_USERNAME=root',
            ]));

        $forge->shouldHaveReceived('updateSiteDeploymentScript')
            ->with(987, 876, implode(' && ', [
                'cd /home/forge/123.dev.deploy.cz',
                'git fetch origin refs/pull/1/merge',
                'git checkout FETCH_HEAD',
                '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
                '$FORGE_PHP artisan migrate --force',
                '$FORGE_PHP artisan queue:restart',
            ]));

        $forge->shouldHaveReceived('obtainLetsEncryptCertificate')
            ->with(987, 876, ['domains' => ['123.dev.deploy.cz']], false);

        $forge->shouldHaveReceived('deploySite')
            ->with(987, 876);

        $forge->shouldHaveReceived('executeSiteCommand')
            ->with(987, 876, ['command' => implode(' && ', [
                'cd /home/forge/123.dev.deploy.cz',
                'php artisan key:generate --force',
                'php artisan storage:link',
                'php artisan db:seed --force --no-interaction',
            ])]);
    }

    /** @test */
    public function deploys_existing_site()
    {
        $serverId = 987;

        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', $serverId);

        Http::fake([
            'https://api.github.com/repos/rockero-cz/foobar/pulls/1' => Http::response([
                'id' => 123,
            ]),
        ]);

        $site = $this->spy(Site::class);
        $site->name = '123.dev.deploy.com';
        $site->shouldReceive('deploySite')
            ->andReturn($site);

        $this->partialMock(Forge::class)
            ->shouldReceive('sites')->withArgs([$serverId])
            ->andReturn([$site]);

        $this->withHeaders(['Authorization' => 'Bearer fake-token'])->post('deploy/foobar/pull/1')->assertOk();
    }

    /** @test */
    public function returns_500_when_subsequent_deployment_is_unsuccessful()
    {
        $serverId = 987;

        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', $serverId);

        Http::fake([
            'https://api.github.com/repos/rockero-cz/foobar/pulls/1' => Http::response([
                'id' => 123,
            ]),
        ]);

        $site = $this->mock(Site::class);
        $site->name = '123.dev.deploy.com';

        $site->shouldReceive('deploySite')
            ->andReturn(null); // <--

        $forge = $this->partialMock(Forge::class);

        $forge->shouldReceive('sites')
            ->withArgs([$serverId])
            ->andReturn([$site]);

        $this->withHeaders(['Authorization' => 'Bearer fake-token'])->post('deploy/foobar/pull/1')->assertStatus(500);
    }

    /** @test */
    public function throws_exception_when_deployment_fails()
    {
        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', 987);

        $forge = $this->mock(Forge::class);

        $forge->shouldReceive('sites')->andReturn([]);
        $forge->shouldReceive('createSite')->andReturn(new Site(['id' => 876, 'serverId' => 987]));
        $forge->shouldReceive('installGitRepositoryOnSite');
        $forge->shouldReceive('siteEnvironmentFile')->andReturn('');
        $forge->shouldReceive('updateSiteEnvironmentFile');
        $forge->shouldReceive('updateSiteDeploymentScript');
        $forge->shouldReceive('obtainLetsEncryptCertificate');
        $forge->shouldReceive('deploySite');

        $forge->shouldReceive('get')
            ->with('servers/987/sites/876/deployment-history')
            ->andReturn([
                'deployments' => [
                    ['status' => 'failed'],
                ],
            ]);

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);

        $this->withHeaders(['Authorization' => 'Bearer fake-token'])->post('deploy/foobar/main');
    }
}