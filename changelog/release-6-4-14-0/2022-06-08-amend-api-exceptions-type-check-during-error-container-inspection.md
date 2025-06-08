---
title: Amend API exceptions type check during error container inspection
issue: NEXT-21933
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException` to fix an issue that occurs during API error response serialization when an exception has been pushed it that is not `instanceof \Shopware\Core\Framework\ShopwareHttpException`
