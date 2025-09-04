---
title: Improve RuntimeException for getSchema on store API
issue: 12296
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
___
# API
* Replaced the raw exception in `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator::getSchema()` with a domain exception for unsupported operations. The Store API does not provide an entity schema endpoint; use `/store-api/_info/openapi3.json` to retrieve the OpenAPI specification. The Admin API entity schema remains available at `/api/_info/entity-schema.json`.
