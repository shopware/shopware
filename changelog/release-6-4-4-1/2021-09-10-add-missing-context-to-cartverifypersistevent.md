---
title: Add missing context to CartVerifyPersistEvent
issue: NEXT-17170
author_github: @Dominik28111
---
# Core
* Added SalesChannelConext to `Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent::__construct()`.
* Added method `Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent::setShouldPersist()`.
