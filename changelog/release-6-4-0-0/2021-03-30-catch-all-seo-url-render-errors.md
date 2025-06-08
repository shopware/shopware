---
title: Catch all SeoUrl render errors
issue: NEXT-14546
---
# Core
* Changed `\Shopware\Core\Content\Seo\SeoUrlGenerator` to catch all Exceptions that may occur during rendering of the SeoUrlTemplate, if invalid templates should be skipped, instead of only TwigErrors.
