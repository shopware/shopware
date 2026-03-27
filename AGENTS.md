# Shopware 6

Shopware is an open-source e-commerce platform with API-first architecture exposing three distinct APIs (Admin, Store, Sync) alongside a built-in Twig-based storefront. It uses a custom Data Abstraction Layer instead of a traditional ORM, an event-driven extension system replacing decorators, and Flow Builder for business automation.

## Project Structure

```
shopware/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   ├── Storefront/               # Frontend
│   └── Elasticsearch/            # Search integration
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.2+, Symfony 7, Doctrine DBAL 4
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend Storefront**: Twig, Bootstrap 5, Webpack 5
- **Database**: MySQL 8+ / MariaDB 10.11+
- **Search**: OpenSearch 2 / Elasticsearch 8
- **Cache**: Redis (optional), Symfony Cache
- **Testing**: PHPUnit, PHPStan, Jest, Playwright

## Shopware Architecture

### NOT Standard Symfony/Doctrine
- **NO Doctrine ORM** - Uses custom Data Abstraction Layer (DAL)
- **NO QueryBuilder** - Use `Criteria` API instead
- **NO Doctrine Annotations** - Use `EntityDefinition` classes
- **NO Doctrine Repositories** - Use `EntityRepository` with DAL

### Extension Pattern Priority
1. **Prefer Events** - EventSubscriberInterface for most extensibility
2. **Use Decorators Only When** - Event timing doesn't fit

### Three Distinct APIs
- `/api/` - Admin API (full CRUD, admin operations)
- `/store-api/` - Store API (customer-facing, storefront)
- `/api/_action/sync` - Sync API (bulk operations)

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                                  |
|------------------------|-------------------------------|----------------------------------------------|
| **PHP** (.php)         | `composer ecs`                | `composer ecs-fix`                           |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                      |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`                  |
| **JS/TS** (Storefront) | `composer eslint:storefront`  | `composer eslint:storefront:fix`             |
| **SCSS**               | `composer stylelint`          | `composer stylelint:[admin\|storefront]:fix` |
| **Twig** (Storefront)  | `composer ludtwig:storefront` | `composer ludtwig:storefront:fix`            |
| **Changelog**          | `composer lint:changelog`     | Manual fix required                          |
| **Snippets**           | `composer translation:lint`   | Manual fix required                          |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`                  |

## Cursor Cloud specific instructions

### Services overview

All services run via Docker Compose (`compose.yaml`). The `web` container (`ghcr.io/shopware/docker-dev:php8.4-node24-caddy`) includes PHP 8.4, Node 24, Caddy, and Composer. All `composer` and `bin/console` commands must be prefixed with `docker compose exec web`.

| Service | Port | Purpose |
|---------|------|---------|
| web | 8000 | Shopware (Caddy + PHP-FPM) |
| database | 3306 | MariaDB |
| adminer | 9080 | DB admin UI |
| mailer | 8025 | Mailpit |
| valkey | — | Cache (Valkey/Redis) |
| opensearch | — | Search (disabled by default) |

### Starting the environment

```bash
sudo dockerd &>/tmp/dockerd.log &
sleep 3
docker compose up -d
```

If the database is fresh (no `shopware` schema), run initial setup:

```bash
docker compose exec web composer setup
```

This runs `composer install`, database install, npm ci for admin/storefront, JS builds, theme compile, and asset install. It takes ~2 minutes.

### Running commands

All PHP/Composer/Node commands run inside the `web` container:

```bash
docker compose exec web <command>
```

See `CONTRIBUTING.md` for the full command reference (`composer ecs`, `composer phpstan`, `composer admin:unit`, `composer storefront:unit`, etc.).

### Key gotchas

- **PHPUnit `--filter` requires `--testsuite`**: Use `docker compose exec web php vendor/bin/phpunit --testsuite unit --filter="YourTestClass"`. Without `--testsuite`, duplicate test-file registration warnings cause "No tests executed".
- **Admin Jest extra args**: `composer admin:unit` does not forward extra CLI args to Jest. To run a subset, use `docker compose exec web bash -c 'cd src/Administration/Resources/app/administration && npx jest --config jest.config.js --testPathPattern="your/pattern"'` after running `docker compose exec web composer framework:schema:dump` first.
- **Admin ESLint pre-existing warning**: There is 1 pre-existing ESLint error in `test/_helper_/componentWrapper/index.js` (missing file extension). This is in the test helper, not in production code.
- **Admin login**: username `admin`, password `shopware`.
- **Storefront watcher**: `docker compose exec web composer watch:storefront` (port 9998). **Admin watcher**: `docker compose exec web composer watch:admin` (port 5173).
- **Docker daemon**: Must be started manually in Cloud VMs with `sudo dockerd`. The VM uses fuse-overlayfs storage driver and iptables-legacy.
