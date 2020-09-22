---
title: Fix sales channel modal button keep moving left
issue: NEXT-10743
---
# Administration
* Removed the `template` tag with condition `v-if="detailType"` in slot `modal-footer` of `sw-sales-channel-modal.html.twig`.
* Added the condition for `detailType` to the individual `sw-button` elements in slot `modal-footer` of `sw-sales-channel-modal.html.twig` to force the the correct re-rendering of the `sw-button` elements inside...
    * `sw_sales_channel_modal_footer_back`
    * `sw_sales_channel_modal_footer_add_channel`
    * `sw_sales_channel_modal_footer_cancel`
