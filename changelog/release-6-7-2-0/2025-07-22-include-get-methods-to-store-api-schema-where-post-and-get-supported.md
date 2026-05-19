---
title: Include GET methods to store API schema where POST and GET supported
issue: https://github.com/shopware/shopware/issues/10897
---
# Core
* Changed `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator` to support custom `x-parameter-groups` component. It allows to define a set of parameters that can be referenced by the group name in the parameters section. The reference will be replaced by the list of parameters that group contains.
* Changed a set of store API endpoints schemas to include already supported `GET` methods.
