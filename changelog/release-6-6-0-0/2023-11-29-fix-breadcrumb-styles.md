---
title: Fix breadcrumb styles
issue: NEXT-32803
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Deprecated SCSS file `Resources/app/storefront/src/scss/page/content/_breadcrumb.scss` will be removed without replacement. Bootstrap container styles are used instead.
* Removed unused css class `breadcrumb-container` in `Resources/app/storefront/src/scss/skin/shopware/component/_breadcrumb.scss`.
* Removed `breadcrumb` class from outer breadcrumb container in `Resources/views/storefront/page/content/index.html.twig` to prevent duplicated bootstrap styles.
* Removed `breadcrumb` class from outer breadcrumb container in `Resources/views/storefront/page/content/product-detail.html.twig` to prevent duplicated bootstrap styles.
