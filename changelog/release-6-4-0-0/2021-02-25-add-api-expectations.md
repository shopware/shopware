---
title: Add API Expectations
issue: NEXT-13357
---
# Core
* Added new Subscriber `\Shopware\Core\Framework\Api\EventListener\ExpectationSubscriber`

___
# API
* Added new header `sw-expect-packages` for admin-api
    * This header can be used to specify as requester that some packages with specific version are available on server side
    * Examples: 
        * `shopware/core:~6.4` -> Expects on server side running at least Shopware 6.4, otherwise the request will fail with an 417
        * `shopware/core:~6.4,swag/paypal:*` => Expects on server side running at least Shopware 6.4 and PayPal is installed, otherwise the request will fail with an 417
    * The delimiter for multiple packages is `,`. Separator for package name and constraint is `:`. 
