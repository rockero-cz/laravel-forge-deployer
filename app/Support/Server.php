<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Database;
use Laravel\Forge\Resources\Site;

class Server
{
    private Collection $sites;
    private Collection $databases;
    private string $serverId;

    public function __construct(private Forge $forge)
    {
        $this->serverId = config('services.forge.server_id');
    }

    /**
     * Find site by domain.
     */
    public function site(string $domain): ?Site
    {
        return $this->sites()->firstWhere('name', $domain);
    }

    /**
     * Find database by name.
     */
    public function database(string $name): ?Database
    {
        return $this->databases()->firstWhere('name', $name);
    }

    /**
     * Get all sites.
     *
     * @return Collection<Site>
     */
    public function sites(): Collection
    {
        $this->sites ??= collect($this->forge->sites($this->serverId));

        return $this->sites;
    }

    /**
     * Get all databases.
     */
    public function databases(): Collection
    {
        $this->databases ??= collect($this->forge->databases($this->serverId));

        return $this->databases;
    }

    /**
     * Get date of the last deployment.
     */
    public function lastDeploymentAt(Site $site): ?Carbon
    {
        $deployments = $this->forge->deploymentHistory($this->serverId, $site->id)['deployments'];

        return !empty($deployments) ? Carbon::parse($deployments[0]['ended_at']) : null;
    }
}
