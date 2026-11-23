---
title: Compare guest credentials case-insensitively
issue: 21209
---

# Core

* Changed `Shopware\Core\Checkout\Order\SalesChannel\OrderRoute` and `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute` to compare the email address and postal code of a guest case-insensitively, as in 6.7.
