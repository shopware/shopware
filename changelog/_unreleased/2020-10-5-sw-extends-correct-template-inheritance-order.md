---
title: sw_extends: Use correct template inheritance order, if extending a different base file.
issue: NEXT-7517
---
# Storefront
* `sw_extends`: Use correct template inheritance order, if extending a different base file.
    * This means that, if a theme overwrites the `@Storefront/storefront/base.html.twig`, extends the original file and also extends other templates that those templates will use the `storefront/base.html.twig` from the theme instead of falling back to the default Storefront template.
