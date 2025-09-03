---
title: Fix intra community electronic invoice
issue: 12182
---
# Core
* Fix intra community electronic invoice where calculated taxes is empty
* Deprecated `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` in favor of `ZugferdDocument::getPriceWithFallback()`
___
# Upgrade Information
## Deprecated `ZugferdDocument::getPrice()`
The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` is deprecated and will be removed in the next major version. Replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.
___ 
# Next Major Version Changes
## Removal of `ZugferdDocument::getPrice()`
The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` was removed, replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.
