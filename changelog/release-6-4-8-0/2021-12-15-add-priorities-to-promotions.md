---
title: Add priorities to promotions
issue: NEXT-16646
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Added `priority` field to promotion, which defaults to 1
___
# Administration
* Added a `sw-number-field` to `sw-promotion-v2-detail-base` to edit the priority of the promotion
___
# Storefront
* Changed `\Shopware\Core\Checkout\Promotion\Gateway\PromotionGateway` to load promotions sorted by there priority
