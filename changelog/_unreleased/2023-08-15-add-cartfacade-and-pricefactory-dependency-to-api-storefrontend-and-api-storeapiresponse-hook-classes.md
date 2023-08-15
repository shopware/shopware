---
title: To get the [CartFacadeHookFactory and PriceFactoryFactory object] in the [api-storefront hook and api-storeapiresponse hook] and to be able to read and manipulate the shopping cart
issue: [form request object] not available in [cart hook], so we make the cart and price hook available in the [api-storefront and api-storeapiresponse hooks]
author: Codixio (Matthias Jakisch)
author_email: support@codixio.com
author_github: codixio
---
# Core
* Changed `src\Storefront\Framework\Script\Api\StorefrontHook.php` added CartFacadeHookFactory::class and PriceFactoryFactory::class, to getServiceIds function.
___
# Storefront
* Changed `src/Core/Checkout/Cart/Hook/CartHook.php` added CartFacadeHookFactory::class and PriceFactoryFactory::class, to getServiceIds function.
