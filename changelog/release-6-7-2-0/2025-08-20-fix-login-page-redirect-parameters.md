---
title: Fix login page redirect parameters
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Changed `@Storefront/storefront/component/account/login.html.twig` and `@Storefront/storefront/component/account/register.html.twig` to json encode iterable `redirectParameters`.
