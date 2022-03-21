---
title: Return cloned line item when quantity is the same
issue: NEXT-20515
author_github: @Dominik28111
---
# Core
* Changed method `Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter::split()` to return cloned line item when quantity is the same to prevent duplicate recalculations.
