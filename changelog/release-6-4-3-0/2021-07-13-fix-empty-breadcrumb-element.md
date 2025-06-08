---
title: Fix empty breadcrumb element
issue: NEXT-16195
---
# Storefront
* Changed `src/Storefront/Resources/views/storefront/layout/breadcrumb.html.twig` to do not display the `<nav aria-label="breadcrumb">` element, if no breadcrumbs are present.
