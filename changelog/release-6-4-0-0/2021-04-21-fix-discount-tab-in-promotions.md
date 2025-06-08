---
title: Fix discount tab in promotions
issue: NEXT-14897
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Removed `discount` from State `swPromotionDetail`
* Removed commit `setDiscounts` from State `swPromotionDetail`
* Removed method `createdComponent` from `sw-promotion-detail-discounts`
* Removed method `loadDiscounts` from `sw-promotion-detail-discounts`
* Changed computed getter/setter `discounts` in `sw-promotion-detail-discounts` to getter only
