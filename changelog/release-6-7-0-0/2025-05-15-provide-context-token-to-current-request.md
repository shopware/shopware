---
title: Provide context token to current request
author: Michael Telgmann
author_github: mitelg
---
# Storefront
* Changed `\Shopware\Storefront\Framework\Routing\StorefrontSubscriber::startSession()` to provide the context token to the current request if it differs from the main request.
___

# Upgrade Information

## StorefrontSubscriber now adds context token to the current request
The `\Shopware\Storefront\Framework\Routing\StorefrontSubscriber::startSession()` method has been updated to provide the context token to the current request if it differs from the main request.
This is especially necessary if a reverse proxy like Varnish or Fastly is used.
Due to loading of the header and footer via ESI, it would otherwise cause the sub requests for those to have a different contexts than the main request.
