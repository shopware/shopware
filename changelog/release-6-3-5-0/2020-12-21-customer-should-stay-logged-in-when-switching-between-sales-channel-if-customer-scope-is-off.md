---
title: Customer should stay logged in when switching between sales channel if customer scope is off
issue: NEXT-12869
---
# Storefront
* Added a new condition in `\Shopware\Storefront\Framework\Routing\StorefrontSubscriber::startSession` to check if the context token should be re-new depend on the customer scope is on or off.
