---
title: Fix intra community electronic invoice
issue: 12182
---
# Core
* Changed price calculation for electronic invoices to correctly handle intra-community purchases where calculated tax is empty.
* Deprecated `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` in favor of `ZugferdDocument::getPriceWithFallback()`
* Changed behaviour inside `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument`: Shopware Core now calls `ZugferdDocument::getPriceWithFallback()`and `ZugferdDocument::getPrice()` is no longer used.
___
# Upgrade Information
## Deprecated `ZugferdDocument::getPrice()`
The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` is deprecated and will be removed in the next major version. Replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.
### Extension impact
If a plugin overrides `ZugferdDocument::getPrice()`, that override will not be executed by the core anymore. Replace it with `ZugferdDocument::getPriceWithFallback()` to be able to make customisations.

___ 
# Next Major Version Changes
## Removal of `ZugferdDocument::getPrice()`
The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` was removed, replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.
