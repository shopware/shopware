---
title: Implement SEO assignment for cms aware
issue: NEXT-24138
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added two new components to allow SEO configuration for `cms-aware` custom entities
    * `sw-generic-seo-general-card`
    * `sw-generic-social-media-card`
* Changed `sw-generic-custom-entity-detail` to use the aforementioned card
* Added SEO properties to `generic_custom_entity` in `generic_custom_entities.d.ts`
