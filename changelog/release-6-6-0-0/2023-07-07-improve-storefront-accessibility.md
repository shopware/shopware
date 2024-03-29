---
title: Improve storefront accessibility
issue: NEXT-32086
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Storefront
* Added missing `autocomplete` attributes to the login and address form templates:
    * `views/storefront/component/account/login.html.twig`
    * `views/storefront/component/address/address-form.html.twig` 
    * `views/storefront/component/address/address-personal-company.html.twig`
* Added missing `theme-color` meta attribute with `sw-background-color` value in `views/storefront/layout/meta.html.twig`
