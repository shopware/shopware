---
title: Improve variants can be assigned to a category
issue: #5263
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @nguyenquocdaile
---
# Administration
* Added `displayVariants` props to display variants in `src/app/component/entity/sw-many-to-many-assignment-card/index.js`.
* Changed `searchItem` method to enable search inherited in `src/app/component/entity/sw-many-to-many-assignment-card/index.js`.
* Added `sw-product-variant-info` component template to `sw_entity_many_to_many_assignment_card_results_list_list_item_label` to display variants list in `src/app/component/entity/sw-many-to-many-assignment-card/sw-many-to-many-assignment-card.html.twig`.
* Changed `productCriteria` computed to get all parent products and variants in `src/module/sw-category/view/sw-category-detail-products/index.js`.
