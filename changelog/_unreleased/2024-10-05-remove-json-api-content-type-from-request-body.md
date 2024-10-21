---
title: Remove JSON API content type from request body
issue: NEXT-38382
---
# Core
* Changed `\Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder` to remove the JSON API content type from the request body, as the request bodies can not be parsed with that content type.