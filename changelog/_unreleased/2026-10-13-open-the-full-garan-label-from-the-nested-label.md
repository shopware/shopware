---
title: Open the full GARAN label from the nested label
issue: 20271
---
# Core
* Added `Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer::garanLabelLinkForLanguage()` and the Twig filter `sw_garan_label_link`, which return the "Your Europe" page about the commercial guarantee of durability.
___
# Storefront
* Added the route `frontend.product.garan-label` to `Shopware\Storefront\Controller\ProductController`, which renders the full GARAN label of a product in `@Storefront/storefront/component/product/garan-label-modal.html.twig`.
* Changed the block `component_line_item_garan_label` in `@Storefront/storefront/component/line-item/element/garan-label.html.twig` to render the nested GARAN label as a button that opens the full label in a modal.
* Changed the block `buy_widget_garan_label_preview` in `@Storefront/storefront/component/buy-widget/buy-widget.html.twig` to render the nested GARAN label as a button that expands the full label, and the block `buy_widget_garan_label_full` to link the full label to "Your Europe".
* Changed `PseudoModalUtil` to move the focus into the modal when an open off-canvas focus-trap pulled it out, and to make a modal wrapper interactive again that an open off-canvas made inert.
___
# Upgrade Information
## Nested GARAN label opens the full label
The nested GARAN label is now a button that shows the full label. On line items it opens a modal loaded from the new route `frontend.product.garan-label` (template `storefront/component/product/garan-label-modal.html.twig`); on the product detail page it expands the full label.

If you override `component_line_item_garan_label`, `buy_widget_garan_label_preview` or `buy_widget_garan_label_full`, take over the new button and link markup.
