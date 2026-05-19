---
title: Fix HTML quirks mode in the Storefront
issue: #4654
---
# Storefront
* Changed `src/Storefront/Resources/views/storefront/base.html.twig` by moving the `isHMRMode` below the doctype declaration, so no empty lines are generated before the doctype declaration.
