---
title: CMS content override indicator
issue: 12131
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Administration
* Deprecated `extractSlotOverrides`, `getCmsPageOverrides` and `deleteSpecificKeys` in the `sw-categeory-detail` component. Their behavior will be handled by the new component `sw-cms-form-sync`.
* Deprecated `getCmsPageOverrides` and `deleteSpecificKeys` in the `sw-product-detail` component. Their behavior will be handled by the new component `sw-cms-form-sync`.
* Added the new component `sw-cms-inherit-wrapper` to indicate and control inherited layout content on content pages. It emits the two events `inheritance:remove` and `inheritance:restore` for individual slot config fields.
