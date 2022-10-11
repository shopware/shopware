---
title: Fix language and country css selectors in storefront
issue: NEXT-23601
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Storefront
* Changed classes to show the correct language and country codes and therefore its corresponding flags in `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig`
* Deprecated british class selector, to apply the swap of language and country codes in `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss`
