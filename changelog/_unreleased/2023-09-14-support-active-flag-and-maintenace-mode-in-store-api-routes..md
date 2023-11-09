---
title: Support active flag and maintenace mode in store api routes.
issue: NEXT-00000
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `validateRequest` in `Shopware\Core\Framework\Api\EventListener\Authentication\SalesChannelAuthenticationListener` class to support active flag and maintenance mode.
* Added `Shopware\Core\Framework\Routing\MaintenanceModeResolver` to abstract maintenance related checks that can be used in both Storefront and store API routes.
* Changed `Shopware\Storefront\Framework\Routing\MaintenanceModeResolver` to use `Shopware\Core\Framework\Routing\MaintenanceModeResolver` where applicable.
* Changed `Shopware\Storefront\Framework\Routing\MaintenanceModeResolver` to use `main` request to retrieve the attribute `SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE` as was already the case for `SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST` and `SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST`. 
* Added `Shopware\Core\Framework\Routing\Event\MaintenanceModeRequestEvent` event in order to determine if a user is allowed to access the page during maintenance mode, e.g. by providing a centralized list of IPs.
* Deprecated constant `ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE` of `Shopware\Core\SalesChannelRequest`. Use constant `ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE` of `Shopware\Core\PlatformRequest` instead.
* Added constant `ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE` to `Shopware\Core\PlatformRequest`.
* Added `\Shopware\Core\Framework\Util\Json::decodeArray` for decoding JSON into arrays 
