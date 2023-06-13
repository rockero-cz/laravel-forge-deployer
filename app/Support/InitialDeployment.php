<?php

namespace App\Support;

use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;

class InitialDeployment
{
    private Forge $forge;

    private Site $site;
    private array $envReplacements;
    private string $deployScript;
    private string $postDeployCommand;

    public function __construct(
        private string $domain,
        private string $database,
        private string $repository,
        private string $branch,
    ) {
        $this->forge = resolve(Forge::class);

        $this->env([
            'APP_ENV' => 'staging',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'https://' . $domain,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => 'root',
        ]);

        $this->after([
            'php artisan key:generate --force',
            'php artisan storage:link',
            'php artisan db:seed --force --no-interaction',
        ]);
    }

    /**
     * Override .env variables.
     */
    public function env(array $values): self
    {
        $this->envReplacements = $values;

        return $this;
    }

    /**
     * Set deployment script.
     */
    public function script(array|string $script): self
    {
        $this->deployScript = $this->prepareCommand($script);

        return $this;
    }

    /**
     * Set command to run after deployment.
     */
    public function after(array|string $command): self
    {
        $this->postDeployCommand = $this->prepareCommand($command);

        return $this;
    }

    /**
     * Run the deployment.
     */
    public function run()
    {
        // Create site
        $site = $this->site = $this->forge->createSite(config('services.forge.server_id'), [
            'domain' => $this->domain,
            'project_type' => 'php',
            'directory' => "/{$this->domain}",
            'isolated' => false,
            'database' => $this->database,
            'php_version' => 'php81',
            'nginx_template' => 2972,
        ]);

        // Install repository
        $this->forge->installGitRepositoryOnSite($site->serverId, $site->id, [
            'provider' => 'github',
            'repository' => config('services.github.owner') . '/' . $this->repository,
            'branch' => $this->branch,
            'composer' => false,
        ]);

        // Update .env
        if ($this->envReplacements) {
            $env = $this->forge->siteEnvironmentFile($site->serverId, $site->id);

            foreach ($this->envReplacements as $key => $value) {
                $env = preg_replace('/^' . $key . '=(.+)?/m', $key . '=' . $value, $env);
            }

            $this->forge->updateSiteEnvironmentFile($site->serverId, $site->id, $env);
        }

        // Update deployment script
        if ($this->deployScript) {
            $this->forge->updateSiteDeploymentScript($site->serverId, $site->id, $this->deployScript);
        }

        // Install SSL
        $this->forge->obtainLetsEncryptCertificate($site->serverId, $site->id, ['domains' => [$this->domain]], false);

        // Run deployment script
        $this->forge->deploySite($site->serverId, $site->id);

        // Throw exception if deployment failed
        $this->ensureSiteIsDeployed();

        // Setup scheduler
        $this->forge->createJob($site->serverId, [
            'command' => "php8.1 /home/forge/{$this->domain}/artisan schedule:run",
            'frequency' => 'minutely',
            'user' => 'forge',
        ], false);

        // Run post deployment command
        if ($this->postDeployCommand) {
            $this->forge->executeSiteCommand($site->serverId, $site->id, ['command' => $this->postDeployCommand]);
        }
    }

    public function ensureSiteIsDeployed(): void
    {
        $deployments = $this->forge->get("servers/{$this->site->serverId}/sites/{$this->site->id}/deployment-history")['deployments'];

        throw_if(! $deployments || $deployments[0]['status'] == 'failed');
    }

    /**
     * Prepare command argument.
     */
    private function prepareCommand(array|string $command): string
    {
        return "cd /home/forge/{$this->domain} && " . (is_array($command) ? implode(' && ', $command) : $command);
    }
}
