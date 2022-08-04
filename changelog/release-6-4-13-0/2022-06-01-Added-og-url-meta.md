---
title: Added og:url meta to Storefront
issue: NEXT-21846
author_email: simon.nitzsche@esera.de
author: SimonNitzsche
author_github: SimonNitzsche
---
# Storefront
* Added `og:url` meta in `@Storefront/storefront/layout/meta.html.twig`.
* Changed `Shopware\Storefront\Page\Navigation\NavigationPageLoader::load` to set `navigationId` and `metaInformation` even if no cms page is assigned.
