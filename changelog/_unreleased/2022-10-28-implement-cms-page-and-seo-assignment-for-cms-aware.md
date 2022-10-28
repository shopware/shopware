---
title: Implement CMS Page and SEO assignment for cms-aware
issue: NEXT-23383
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added the `sw-generic-cms-page-assignment` component for selecting and overriding cms-pages
* Changed `sw-generic-custom-entity-detail` to display the `sw-generic-cms-page-assignment` when the custom entity has the `cms-aware` flag
* Changed `sw-generic-custom-entity-detail` to redirect to `sw.cms.create` when `sw-generic-cms-page-assignment` emits the `create-layout-event`
