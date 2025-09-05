---
title: Improve RuntimeException for getSchema on store API
issue: 12296
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
___
# API
* Changed the raw exception in `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator::getSchema()` with a proper domain exception for `unsupportedStoreApiSchemaEndpoint`.
