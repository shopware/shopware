---
title: Pin promotions for admin orders
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Added pinning promotion feature to `PromotionCollector`. Existing set of promotions will be kept and changed even if conditions are not matching anymore
* Changed `PromotionCollector` to add deletion notices for automatic promotions in the recalculation process
* Changed `PromotionCollector` to inherit any line-item extension structs from old discount line-items
* Added `RecalculationService::recalculate` to return all cart errors that occurred during recalculation
* Deprecated `RecalculationService::recalculateOrder` in favour of `RecalculationService::recalculate`
* Added `RecalculationService::applyAutomaticPromotions` to make use of pinning promotions
* Deprecated `RecalculationService::toggleAutomaticPromotion` in favour of `RecalculationService::applyAutomaticPromotions`
* Changed `PromotionDeliveryCalculator` to restore price definitions of fake line-items
___
# API
* Added route `api.action.order.apply-automatic-promotions`
* Deprecated route `api.action.order.toggle-automatic-promotions` in favour of `api.action.order.apply-automatic-promotions`
___
# Administration
* Changed `sw-order-promotion-field` to have its own card in the general instead of details tab
* Changed `sw-order-detail` to expose all cart errors in the form of notifications
* Changed `sw-order-detail` to _provide_ a function for adding cart errors from child components
___
# Upgrade Information
## Pinning promotions in administration
When an _existing_ order is changed in administration, the promotions are pinned.
No changes will be made to the promotions, other than recalculating the price, unless done manually.
This means that when changing the order:
* No promotions are _automatically_ added
* No promotions are _automatically_ removed
* Disabled promotions are kept and recalculated correctly
* Adding promotion codes works as expected

In addition, the switch for toggling automatic promotions is replaced by a button.
The button will reapply _automatic_ promotions by:
* Removing _automatic_ promotions whose conditions aren't met anymore
* Adding _automatic_ promotions whose conditions apply to the order
