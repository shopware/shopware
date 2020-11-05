---
title: Check `sw-api-compatibility` header in `ApiVersionConverter`
issue: NEXT-11039
___
# API
* Changed `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter` to ignore deprecations if the header `sw-api-compatibility` is set. Before this was only checked in the `\Shopware\Core\Framework\Api\Converter\DefaultApiConverter`. Custom `\Shopware\Core\Framework\Api\Converter\ApiConverter` had to check it themself.
