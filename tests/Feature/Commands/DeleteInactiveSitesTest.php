<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Http;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use Mockery\MockInterface;
use Tests\TestCase;

class DeleteInactiveSitesTest extends TestCase
{
    protected Forge|MockInterface $forge;

    public function setUp(): void
    {
        parent::setUp();

        config()->set('services.github.owner', 'rockero-cz');
        config()->set('services.forge.domain', 'deploy.com');
        config()->set('services.forge.server_id', 987);

        // Setup fake returns
        $this->forge = $this->spy(Forge::class);
    }

    /** @test */
    public function deletes_sites_with_closed_pull_requests()
    {
        $site = $this->spy(Site::class);
        $site->name = '9999.dev.rockero.cz';
        $site->repository = 'acme';

        $this->forge->shouldReceive('sites')->andReturn([$site]);

        Http::fake([
            '*/repos/acme/pulls' => Http::sequence()
                ->push([['id' => '9999', 'state' => 'open']])
                ->push([])
                ->push([['id' => '9998', 'state' => 'open']])
                ->push([['id' => '9999', 'state' => 'closed']]),
        ]);

        // Open pull request - should not be deleted
        $this->artisan('app:delete-inactive-sites');

        $site->shouldNotHaveReceived('delete');

        // No pull requests- should be deleted
        $this->artisan('app:delete-inactive-sites');

        $site->shouldHaveReceived('delete');

        // Different pull request open - should be deleted
        $this->artisan('app:delete-inactive-sites');

        $site->shouldHaveReceived('delete');

        // Closed pull request - should be deleted
        $this->artisan('app:delete-inactive-sites');

        $site->shouldHaveReceived('delete');
    }

    /** @test */
    public function deletes_sites_without_deployments_in_last_60_days()
    {
        $site = $this->spy(Site::class);
        $site->name = 'acme.dev.rockero.cz';
        $site->repository = 'acme';
        $site->repositoryBranch = 'main';

        $this->forge->shouldReceive('sites')->andReturn([$site]);
        $this->forge->shouldReceive('deploymentHistory')
            ->andReturn([
                'deployments' => [
                    [
                        'ended_at' => now()->subDays(61)->toDateTimeString(),
                    ],
                ],
            ]);

        // Open pull request - should not be deleted
        $this->artisan('app:delete-inactive-sites');

        $site->shouldHaveReceived('delete');
    }
}
