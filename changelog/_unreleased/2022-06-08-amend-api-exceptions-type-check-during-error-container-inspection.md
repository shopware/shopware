---
title: Amend API exceptions type check during error container inspection
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Fixed issue that occurs during API error response serialization when an exception has been pushed onto `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException` that is not `instanceof \Shopware\Core\Framework\ShopwareHttpException`
