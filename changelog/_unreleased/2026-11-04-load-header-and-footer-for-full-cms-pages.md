---
title: Load header and footer for full CMS pages
issue: 21514
---

# Storefront

* Changed `Shopware\Storefront\Controller\CmsController::pageFull` to load the header and footer via the generic page loader when the feature flag `cache_rework` is not active.
  The full CMS page rendered by the route `frontend.cms.page.full`, for example the page behind the revocation button, now shows the main navigation, the footer navigation and the payment and shipping logos like every other Storefront page.
