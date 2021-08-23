---
title: Enable the editing of promotions in existing orders
issue: NEXT-13002
---
# Core
* Changed const `\Shopware\Core\Checkout\Cart\Order\OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS` to public
* Added methods `addPromotionLineItem` and `toggleAutomaticPromotion` in `\Shopware\Core\Checkout\Cart\Order\RecalculationService`
___
# API
* Added new routes `/api/_action/order/{orderId}/promotion-item` and `/api/_action/order/{orderId}/toggleAutomaticPromotions`
___
# Administration
* Added blocks `sw_order_detail_base_line_items_switch_promotions` and `sw_order_detail_base_line_items_voucher_field` in `src/module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig`
* Added following methods to component `sw-order-detail-base`
  - `deleteAutomaticPromotion`
  - `toggleAutomaticPromotions`
  - `handlePromotionCodeTags`
  - `onSubmitCode`
  - `handlePromotionResponse`
  - `onRemoveExistingCode`
  - `getLineItemByPromotionCode`
  - `updatePromotionList`
* Added following computed properties to component `sw-order-detail-base`
  - `orderLineItemRepository`
  - `disabledAutoPromotionVisibility`
  - `hasLineItem`
  - `currency`
  - `promotionCodeLineItems`
  - `hasAutomaticPromotions`
  - `promotionCodeTags`
* Added watcher `order.lineItems` to component `sw-order-detail-base`
* Added mixin `notification` to component `sw-order-detail-base`
* Added snippet `sw-order.detailBase.textPromotionRemoved`
* Added methods `addPromotionToOrder` and `toggleAutomaticPromotions` to `order.api.service`
