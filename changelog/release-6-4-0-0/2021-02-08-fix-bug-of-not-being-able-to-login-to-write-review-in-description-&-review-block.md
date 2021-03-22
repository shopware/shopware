---
title: Fix bug of not being able to login to write review in Description & Review block
issue: NEXT-13631
---
# Storefront
* Changed `src/Storefront/Resources/views/storefront/component/review/review-login.html.twig` to pass `redirectParameters` to `activeRoute`
