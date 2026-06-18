# Get involved

Shopware is available under the MIT license. If you want to contribute code (features or bug fixes), you have to create a pull request and include valid license information. Contribute your code under the MIT license.

If you want more details about available licensing or the contribution agreements we offer, you can contact us at <contact@shopware.com>.

## Contributing to the Shopware code base

If you want to learn how to contribute code to Shopware, please refer to [Contributing Code](https://developer.shopware.com/docs/resources/guidelines/code/contribution.html).
Also, make sure that if you change something in a manner that is relevant to external developers please describe your change in a meaningful way. For more information refer to [this document](https://github.com/shopware/shopware/blob/trunk/delivery-process/documenting-a-release.md).

## Docker Setup (Recommended)

The repository includes a Docker Compose setup that provides all required services (PHP, MySQL, OpenSearch, Redis, Mailpit). This is the **recommended** way to set up your development environment.

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/)
- Git

### Getting Started

Checkout the repository and start the containers:

```bash
git clone git@github.com:shopware/shopware.git
cd shopware
docker compose up -d
```

Once the containers are running, execute all commands inside the `web` container by prefixing them with `docker compose exec web`:

```bash
docker compose exec web composer setup
```

This runs `composer setup` which performs the full setup:
1. Installs PHP dependencies (`composer install -o`)
2. Creates the database and installs Shopware (`init:db`)
3. Installs JavaScript dependencies (`init:js`)
4. Builds all frontend assets (`build:js`)
5. Activates the Storefront theme

After the setup is complete, you can access the application:

| Service      | URL                                   |
|-------------|---------------------------------------|
| Storefront   | [http://localhost:8000](http://localhost:8000) |
| Administration | [http://localhost:8000/admin](http://localhost:8000/admin) |
| Database (Adminer) | [http://localhost:9080](http://localhost:9080) |
| Mailpit (Mail catcher) | [http://localhost:8025](http://localhost:8025) |

**Default login**: Username `admin`, Password `shopware`.

**Database credentials (Adminer)**:
- Server: `database`
- Username: `root`
- Password: `root`

### Common Setup Commands

Depending on what you're working on, you may not need the full `composer setup`. Here are the individual steps:

| Command | Description |
|---------|-------------|
| `composer setup` | Full setup (install, database, JS, build) |
| `composer install -o` | Install PHP dependencies with optimized autoloader |
| `composer init:db` | Drop existing database and reinstall Shopware |
| `composer init:js` | Install all JavaScript dependencies (admin + storefront + extensions) |
| `composer init:testdb` | Initialize the test database |
| `composer build:js` | Build all frontend assets (admin + storefront) |
| `composer build:js:admin` | Build only the administration frontend |
| `composer build:js:storefront` | Build only the storefront frontend |
| `composer reset` | Reset database and rebuild all assets |

### Development Watchers

For frontend development, use the watchers to get hot module replacement (HMR):

```bash
# Administration watcher (available at http://localhost:5173)
docker compose exec web composer watch:admin

# Storefront watcher (available at http://localhost:9998)
docker compose exec web composer watch:storefront
```

The watched Administration is available at [http://localhost:5173](http://localhost:5173) and the watched Storefront at [http://localhost:9998](http://localhost:9998).

### Configuring PHPStorm to Run in Docker

In PHPStorm you need to create a new PHP Interpreter from Docker Compose and select the `web` service.
Make sure you set the Lifecycle to **Connect to existing container** to speed up test execution.

### Running tools

You will need to prepend to all commands `docker compose exec web` to run the commands in the container. For example:

```bash
docker compose exec web composer setup
```

For all available commands see [Command Overview](#command-overview).

### Using Dev Containers in VS Code / Cursor

If you are using VS Code, Cursor AI, or any VS Code-based IDE, you can use the Dev Containers feature to work directly inside the container with full terminal support and other improvements.

**Prerequisites**:
- Install the [Dev Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension.
- Open the repository in your IDE.
- From the command palette (`Ctrl + Shift + P` / `Cmd + Shift + P`), run: **Dev Containers: Reopen in Container**.

The IDE will restart with your environment set up inside the container. The container starts automatically each time you reopen the project. The terminal and other tools (including AI agent commands in Cursor) will use the container shell. PHP tooling and other extensions will be configured for optimal use with Shopware.

### Changing Environment Variables

You can create a `.env` file to override the default environment variables. These are loaded automatically without having to restart the containers.

### Enable Profiler / Debugging (XDebug)

To enable XDebug, create a `compose.override.yaml`:

```yaml
services:
    web:
        environment:
            - XDEBUG_MODE=debug
            - XDEBUG_CONFIG=client_host=host.docker.internal
            - PHP_PROFILER=xdebug
```

Then run `docker compose up -d` to apply the changes.

The profiler also supports `blackfire`, `tideways`, and `pcov`. For `tideways` and `blackfire` you need a separate container:

```yaml
services:
    web:
        environment:
            - PHP_PROFILER=blackfire
    blackfire:
        image: blackfire/blackfire:2
        environment:
            BLACKFIRE_SERVER_ID: XXXX
            BLACKFIRE_SERVER_TOKEN: XXXX
```

### Using OrbStack Routing

Instead of using regular ports, you can use OrbStack's URL generation feature. OrbStack generates URLs like `https://web.orb.local` for each running container, allowing easier access without managing port mappings. This also lets you run multiple Shopware instances simultaneously without port conflicts.

Create a `compose.override.yaml`:

```yaml
services:
    web:
        ports: !override []
        environment:
            APP_URL: https://web.sw-trunk.orb.local
            SYMFONY_TRUSTED_PROXIES: 'private_ranges'
    database:
        ports: !override []
    adminer:
        ports: !override []
    valkey:
        ports: !override []
    mailer:
        ports: !override []
    opensearch:
        ports: !override []
```

The `APP_URL` follows the pattern `web.<project-name>.orb.local` — the project name is your folder name. So for a folder called `shopware`, the URL becomes `https://web.shopware.orb.local`. You can also visit `https://orb.local` in your browser to see all running containers and their URLs.

The `SYMFONY_TRUSTED_PROXIES` setting is required to access Shopware via HTTPS using `.orb.local` domains.

For the Storefront watcher with OrbStack, set the `PROXY_URL` environment variable:

```bash
docker compose run --rm -p 9998:9998 -p 9999:9999 -e PROXY_URL=http://localhost web composer watch:storefront
```

And for the Admin watcher:

```bash
docker compose run --rm -p 5173:5173 -e PROXY_URL=http://localhost web composer watch:admin
```

## Command Overview

All commands below should be run inside the Docker container prefixed with `docker compose exec web`.

### Setup & Build

| Command | Description |
|---------|-------------|
| `composer setup` | Full setup: install dependencies, init DB, install JS, build assets |
| `composer install -o` | Install PHP dependencies with optimized autoloader |
| `composer init:db` | Drop existing database and reinstall Shopware with demo data |
| `composer init:js` | Install all JavaScript dependencies (admin + storefront + extensions) |
| `composer init:testdb` | Initialize the test database |
| `composer build:js` | Build all frontend assets (admin + storefront) |
| `composer build:js:admin` | Build only the administration frontend |
| `composer build:js:storefront` | Build only the storefront frontend |
| `composer reset` | Reset database and rebuild all assets (quick full reset) |

### Development Watchers

| Command | Description |
|---------|-------------|
| `composer watch:admin` | Start the administration dev server with HMR (http://localhost:5173) |
| `composer watch:storefront` | Start the storefront dev server with HMR (http://localhost:9998) |
| `composer storefront:dev-server` | Start the storefront dev server (without HMR proxy) |
| `composer storefront:storybook` | Start Storybook for storefront component development |

### Linting & Code Style

| Command | Description |
|---------|-------------|
| `composer lint` | Run all linters (stylelint + ESLint + CS + translations) |
| `composer cs` | Check PHP code style (dry-run) |
| `composer cs-fix` | Fix PHP code style automatically |
| `composer eslint` | Run all ESLint checks (admin + storefront) |
| `composer eslint:admin` | Run ESLint for the administration |
| `composer eslint:admin:fix` | Auto-fix ESLint issues in the administration |
| `composer eslint:storefront` | Run ESLint for the storefront |
| `composer eslint:storefront:fix` | Auto-fix ESLint issues in the storefront |
| `composer stylelint` | Run Stylelint for all SCSS files |
| `composer stylelint:admin:fix` | Auto-fix Stylelint issues in the administration |
| `composer stylelint:storefront:fix` | Auto-fix Stylelint issues in the storefront |
| `composer ludtwig:storefront` | Lint Twig templates in the storefront |
| `composer ludtwig:storefront:fix` | Auto-fix Twig template issues |
| `composer format:admin` | Check Prettier formatting in the administration |
| `composer format:admin:fix` | Auto-fix Prettier formatting in the administration |
| `composer lint:snippets` | Validate translation snippet files |
| `composer translation:lint` | Validate translations |

### Static Analysis

| Command | Description |
|---------|-------------|
| `composer phpstan` | Run PHPStan static analysis |
| `composer static-analyze` | Run PHPStan on the `src/` directory |
| `composer rector` | Run Rector for automated PHP refactoring |
| `composer phpstan-errors-by-area` | Print PHPStan baseline errors grouped by area |

### Testing

| Command | Description |
|---------|-------------|
| `composer phpunit` | Run PHPUnit test suite |
| `composer admin:unit` | Run Jest unit tests for the administration |
| `composer admin:unit:watch` | Run admin unit tests in watch mode |
| `composer storefront:unit` | Run Jest unit tests for the storefront |
| `composer storefront:unit:watch` | Run storefront unit tests in watch mode |
| `composer storefront:components:unit` | Run storefront component unit tests |
| `composer storefront:components:unit:watch` | Run storefront component tests in watch mode |
| `composer phpbench` | Run PHPBench performance benchmarks |

### Other Utilities

| Command | Description |
|---------|-------------|
| `composer admin:generate-entity-schema-types` | Generate TypeScript types from entity schema |
| `composer admin:generate-blocks-list` | Generate the administration blocks list |
| `composer admin:code-mods` | Run administration code mods |
| `composer framework:schema:dump` | Dump the entity schema for the administration |
| `composer bc-check` | Run backward compatibility checks |
| `composer check:license` | Check license compliance of dependencies |
| `composer make:coverage` | Generate test coverage for changed PHP files |

### Common Development Workflows

**Working on the administration:**
```bash
# Start the admin watcher before editing; changes are reflected at http://localhost:5173
docker compose exec web composer watch:admin

# Before committing, run linting and tests
docker compose exec web composer eslint:admin:fix
docker compose exec web composer admin:unit
```

**Working on the storefront:**
```bash
# Start the storefront watcher before editing; changes are reflected at http://localhost:9998
docker compose exec web composer watch:storefront

# Before committing, run linting and tests
docker compose exec web composer eslint:storefront:fix
docker compose exec web composer stylelint:storefront:fix
docker compose exec web composer storefront:unit
```

**Working on PHP backend:**
```bash
# After changing PHP code, the container picks it up automatically
# Run static analysis and tests before committing
docker compose exec web composer phpstan
docker compose exec web composer cs-fix
docker compose exec web composer phpunit
```

**Full reset after switching branches:**
```bash
docker compose exec web composer setup
```

**Quick asset rebuild after JS/SCSS changes (without watcher):**
```bash
docker compose exec web composer build:js
```

## Documentation

Developer documentation for Shopware is available [here](https://developer.shopware.com/docs/). You can also contribute to the documentation by submitting your pull requests to [this repository](https://github.com/shopware/docs).

## Translations

Shopware translations are done by the community and can be installed from the plugin store. If you wish to improve Shopware's translations, you can do so in our [Crowdin project page](https://crowdin.com/project/shopware6).
