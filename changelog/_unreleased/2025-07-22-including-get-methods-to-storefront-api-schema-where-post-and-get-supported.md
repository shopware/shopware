---
title: Including get methods to storefront api schema where post and get supported
issue: https://github.com/shopware/shopware/issues/10897
---
# Core
* Changed `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator` to automatically add `get` methods in the storefront api schema where `post` and `get` are supported.
