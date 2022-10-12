---
title: Deprecate database management API endpoints
issue: NEXT-23226
---
# Core
* Deprecated `\Shopware\Core\Framework\Migration\Api\MigrationController` the controller and all it's routes will be removed in v6.5.0.0.
___
# API
* Deprecated `/api/_action/database` API-endpoint. The endpoint will be removed in v6.5.0.0. Database migrations should be only executed over the CLI.
___
# Next Major Version Changes
## Removal of `/api/_action/database`
The `/api/_action/database` endpoint was removed, this means the following routes are not available anymore:
* `POST /api/_action/database/sync-migration`
* `POST /api/_action/database/migrate`
* `POST /api/_action/database/migrate-destructive`

The migrations can not be executed over the API anymore. Database migrations should be only executed over the CLI.
