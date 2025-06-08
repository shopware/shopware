---
title: Implement blog category type
issue: NEXT-22646
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed `sw-category-view/index.js` and `sw-category-view/sw-category-view.html.twig` to display the `Posts` Tab.
* Added `sw-category-detail-custom-entity.html.twig`, `sw-category-detail-custom-entity.scss` and `sw-category-detail-custom-entity/index.ts` to select a custom entity and custom entity instances for the category type `custom_entity`
* Added a nested route for the `Post` tab to `sw-category/index.js`
* Added the necessary snippets for the new category type `Post` and the associated settings card
