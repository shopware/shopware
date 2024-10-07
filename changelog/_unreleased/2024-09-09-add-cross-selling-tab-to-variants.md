---
title: Add cross selling tab to variants
issue: NEXT-13637
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `sw-product-detail.html.twig` to show cross selling tab for variants.
* Added `isInherited` and its corresponding watcher and methods to `sw-product-detail-cross-selling` component to add inheritance functionality.
* Deprecated block `sw_product_detail_empty_state` in `sw-product-detail-cross-selling.html.twig`. Use `sw_product_detail_cross_selling_empty_state_card` instead.
* Deprecated block `sw_product_detail_empty_state_cross_selling_add` in `sw-product-detail-cross-selling.html.twig`. Use `sw_product_detail_cross_selling_empty_state_actions` instead.
* Added missing blocks to `sw-product-detail-cross-selling.html.twig`:
    - `sw_product_detail_cross_selling_empty_state`
    - `sw_product_detail_cross_selling_empty_state_icon`
    - `sw_product_detail_cross_selling_empty_state_actions_add`
* Added new blocks to `sw-product-detail-cross-selling.html.twig`:
    - `sw_product_detail_cross_selling_restore_inheritance`
    - `sw_product_detail_cross_selling_empty_state_content`
    - `sw_product_detail_cross_selling_empty_state_content_child`
    - `sw_product_detail_cross_selling_empty_state_content_child_inherited`
    - `sw_product_detail_cross_selling_empty_state_content_child_inherited_link`
    - `sw_product_detail_cross_selling_empty_state_content_child_not_inherited`
    - `sw_product_detail_cross_selling_empty_state_content_empty`
    - `sw_product_detail_cross_selling_empty_state_inherit_switch`
    - `sw_product_detail_cross_selling_modal_restore_inheritance`
    - `sw_product_detail_cross_selling_modal_restore_inheritance_text`
    - `sw_product_detail_cross_selling_modal_restore_inheritance_footer`
    - `sw_product_detail_cross_selling_modal_restore_inheritance_action_cancel`
    - `sw_product_detail_cross_selling_modal_restore_inheritance_action_restore`
* Added new and removed unused styles in `sw-product-detail-cross-selling.scss`.
* Added new snippets:
    - `sw-product.crossselling.inheritedEmptyStateDescription`
    - `sw-product.crossselling.notInheritedEmptyStateDescription`
    - `sw-product.crossselling.linkCrossSellingsOfParent`
    - `sw-product.crossselling.inheritSwitchLabel`
    - `sw-product.crossselling.buttonRestoreCrossSellingInheritance`
    - `sw-product.crossselling.restoreInheritanceConfirmTitle`
    - `sw-product.crossselling.restoreInheritanceConfirmText`
    - `sw-product.crossselling.restoreInheritanceButtonCancel`
    - `sw-product.crossselling.restoreInheritanceButtonRestore`
