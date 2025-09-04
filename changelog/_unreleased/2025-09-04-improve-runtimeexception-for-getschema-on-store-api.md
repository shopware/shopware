---
title: Improve RuntimeException for getSchema on store API
issue: 12296
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
___
# API
* Changed the raw exception in `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator::getSchema()` with a domain exception for `unsupportedStoreApiSchemaEndpoint`. So it throws the Message: `The store API does not support the entity schema endpoint. Use /store-api/_info/openapi3.json for the OpenAPI specification. The entity schema endpoint is only available for the admin API.`.
