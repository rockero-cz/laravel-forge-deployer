<picture>
    <source
        media="(prefers-color-scheme: dark)"
        srcset="https://banners.beyondco.de/Laravel%20Forge%20Deployer.png?theme=dark&pattern=plus&style=style_1&description=Let+deployer+take+care+of+your+dev/staging+Laravel+sites.&md=1&showWatermark=0&fontSize=75px&images=https%3A%2F%2Fforge.laravel.com%2Fdocs%2Fassets%2Fimg%2Flogo.svg&widths=500"
    />
      <img alt="Banner" src="https://banners.beyondco.de/Laravel%20Forge%20Deployer.png?theme=light&pattern=plus&style=style_1&description=Let+deployer+take+care+of+your+dev/staging+Laravel+sites.&md=1&showWatermark=0&fontSize=75px&images=https%3A%2F%2Fforge.laravel.com%2Fdocs%2Fassets%2Fimg%2Flogo.svg&widths=500">
</picture>

# Laravel Forge Deployer

[![Rockero](https://img.shields.io/badge/Rockero-yellow)](https://rockero.cz)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

Our deployer simplifies management of your staging and development client sites on your `Forge` server by allowing easy deployment of dev/staging branches and pull requests.

If your repository's site does not exist in Forge, our deployer will automatically create the site and handle the initial setup with repository, database, and environment.

**URL examples of deployed applications:**

For `main` branch:

```bash
https://{your-application}.dev.{your-domain}.com
```

For `staging` branch:

```bash
https://{your-application}.staging.{your-domain}.com
```

For pull requests:

```bash
https://{your-application}-{pull-request-number}.dev.{your-domain}.com
```

## Setup

1. Begin by creating a new repository in your GitHub account and use this repostitory as the template.

2. Next, create a new `Forge` site for the deployer and perform the initial deployment.

3. To configure your project, please fill in the required values for your `.env` file below:

```
# Your custom deployer token for requests verification, you will use it in GitHub workflows.
DEPLOYER_TOKEN=

# Credentials of your `Forge` and also ID of the server, where you would like to have your dev/staging sites.
FORGE_API_KEY=
FORGE_SERVER_ID=
FORGE_DOMAIN=

# GitHub tokens are used to automatically add comments with application URLs to your pull requests.
GITHUB_OWNER=
GITHUB_TOKEN=
```

4. Add the following job to your GitHub workflows using the code below:

```yaml
deploy:
    runs-on: ubuntu-latest

    steps:
        - name: Deployment
        uses: fjogeleit/http-request-action@master
        with:
            url: "http://your-deployer.com/deploy/${{ github.event.repository.name }}/event/${{ github.event_name }}"
            method: "POST"
            bearerToken: ${{ secrets.YOUR_DEPLOYER_TOKEN }}
            timeout: 600000
            data: '{"branch": "${{ github.ref_name }}", "number": "${{ github.event.pull_request.number }}"}'
```

The deployer is now ready to deploy your commits upon request. Happy deploying!
