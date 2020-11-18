---
title: Fix error on submitting product review when href lang is active
issue: NEXT-10405
---
# Core
* Changed method `forwardToRoute()` of the `\Shopware\Storefront\Controller\StorefrontController` to add `routeParams` into request attributes.
