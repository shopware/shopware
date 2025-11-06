---
title: Fix flaky storefront test for active route parameters
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `activeRouteParameters` JavaScript variable in `src/Storefront/Resources/views/storefront/layout/meta.html.twig` to always contain valid JSON instead of `null` when route parameters are missing during redirects, fixing flaky test `StorefrontControllerTest::testActiveRouteParamsAreProperlyEscaped`.
