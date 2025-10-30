---
title: Don't expose context token in twig rendering context
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\SalesChannelContext::getToken()` to throw an exception if called in twig rendering context, this prevents the token from being exposed in rendered HTML, which might lead to security vulnerabilities.
___
# Upgrade Information
## `context.token` is no longer available in twig rendering context

The `context.token` variable is no longer available in twig rendering context to prevent potential security vulnerabilities. If you need to access the token, consider using alternative methods that do not expose it in the rendered HTML.
Usually inside the Twig storefront there is no need to handle the context token manually, as it is handled automatically via the session handling in the Storefront.