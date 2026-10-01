---
title: Replace custom CSS declarations from the `_header.scss` file by bootstrap helper classes
issue: NEXT-00
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated custom CSS declarations for selector `.header-cart-total`. Bootstrap helper classes will be used instead inside `storefront/layout/header/actions/cart-widget.html.twig`
* Deprecated custom CSS declarations for selector `.header-logo-col`. Bootstrap helper classes will be used instead inside `storefront/layout/header/header.html.twig`
* Deprecated custom CSS declarations for selector `.header-search`. Bootstrap helper classes will be used instead inside `storefront/layout/header/search.html.twig`
* Deprecated custom CSS declarations for selector `.header-logo-main` and `.header-logo-picture`. Bootstrap helper classes will be used instead inside `storefront/layout/header/logo.html.twig`
* Deprecated not applied CSS declaration `width: 100%` for selector `.header-logo-main-link`
___
# Upgrade Information
* Deprecated custom CSS declarations for selectors `.header-cart-total`, `.header-logo-col`, `.header-search`, `.header-logo-main-link`, `.header-logo-main` and `.header-logo-picture`  and replaced them by Bootstrap helper classes in the corresponding templates.
