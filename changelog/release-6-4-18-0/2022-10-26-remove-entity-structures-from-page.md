---
title: Remove entity structure in landing page
issue: NEXT-20646
---
# Storefront
* Deprecated `cmsPage` and corresponding methods in `Shopware\Storefront\Page\LandingPage\LandingPage` use `LandingPage::getLandingPage()::getCmsPage()` instead.
* Deprecated `customFields` and corresponding methods in `Shopware\Storefront\Page\LandingPage\LandingPage` use `LandingPage::getLandingPage()::getCustomFields()` instead.
* Deprecated `EntityCustomFieldsTrait` in `Shopware\Storefront\Page\LandingPage\LandingPage`.
* Added `landingPage` in `Shopware\Storefront\Page\LandingPage\LandingPage`
* Added `landingPage` variable to `storefront/page/content/detail.html.twig`
