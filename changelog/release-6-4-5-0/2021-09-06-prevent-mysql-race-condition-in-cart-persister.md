---
title: Prevent MySQL race condition in CartPersister
issue: NEXT-13641
---
# Core
* Changed `\Shopware\Core\Checkout\Cart\CartPersister::save()` to wrap queries into a transaction and use retryable queries to prevent MySQL race condition.
