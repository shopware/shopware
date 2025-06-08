---
title: Search by product number should redirect to detail page
issue: NEXT-37121
---
# Storefront
* Changed method `\Shopware\Storefront\Controller\SearchController::handleFirstHit` to redirect to the detail page if the the product number is matched with search term instead of forwarding.