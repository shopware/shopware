---
title: Fix accessibility issues in address manager
issue: NEXT-40164
---
# Storefront
* Added new label `account.addressOptionsBtn` for address options menu.
* Added proper aria label to options menu button of address manager in `address-manager-item.html.twig` and `address-item.html.twig`.
* Changed id attribute of these buttons to unique names to prevent id naming conflicts.
* Changed headline of address list in `addresses-base.html.twig` to `<h2>` for proper headline structure.
* Added new general snippet `global.default.close` for close buttons and replaced all occurrences of hard-coded "Close" snippets.
