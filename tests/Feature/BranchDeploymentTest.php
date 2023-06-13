<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use RuntimeException;
use Tests\TestCase;

class BranchDeploymentTest extends TestCase
{
    use WithoutMiddleware;

    /** @test */
    public function deploys_main_branch()
    {
        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', 987);

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
        $this->post('deploy/foobar/main')->assertOk();

        // Assert methods have been called
        $forge->shouldHaveReceived('createSite')
            ->with(987, [
                'domain' => 'foobar.dev.deploy.com',
                'project_type' => 'php',
                'directory' => '/foobar.dev.deploy.com',
                'isolated' => false,
                'database' => 'dev_foobar',
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
                'APP_URL=https://foobar.dev.deploy.com',
                'DB_DATABASE=dev_foobar',
                'DB_USERNAME=root',
            ]));

        $forge->shouldHaveReceived('updateSiteDeploymentScript')
            ->with(987, 876, implode(' && ', [
                'cd /home/forge/foobar.dev.deploy.com',
                'git reset --hard && git clean -df',
                'git pull origin main',
                '$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader',
                '$FORGE_PHP artisan migrate --force',
                '$FORGE_PHP artisan queue:restart',
            ]));

        $forge->shouldHaveReceived('obtainLetsEncryptCertificate')
            ->with(987, 876, ['domains' => ['foobar.dev.deploy.com']], false);

        $forge->shouldHaveReceived('deploySite')
            ->with(987, 876);

        $forge->shouldHaveReceived('executeSiteCommand')
            ->with(987, 876, ['command' => implode(' && ', [
                'cd /home/forge/foobar.dev.deploy.com',
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

        $site = $this->spy(Site::class);
        $site->name = 'foobar.dev.deploy.com';
        $site->shouldReceive('deploySite')
            ->andReturn($site);

        $this->partialMock(Forge::class)
            ->shouldReceive('sites')->withArgs([$serverId])
            ->andReturn([$site]);

        $this->post('deploy/foobar/main')->assertOk();
    }

    /** @test */
    public function returns_409_when_subsequent_deployment_is_unsuccessful()
    {
        $serverId = 987;

        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', $serverId);

        $site = $this->spy(Site::class);
        $site->name = 'foobar.dev.deploy.com';

        $site->shouldReceive('deploySite')->andReturn(null); // <--

        $forge = $this->partialMock(Forge::class);

        $forge->shouldReceive('sites')->withArgs([$serverId])->andReturn([$site]);

        $this->post('deploy/foobar/main')->assertStatus(409);
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

        $this->post('deploy/foobar/main');
    }
}
