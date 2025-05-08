---
title: Add meta author configuration and use it in frontend
issue: NEXT-40034
author: Fabian Blechschmidt
author_github: @Schrank
---
# Storefront
* Added a configuration for a default `metaAuthor` in the basic information section see `Admin > Settings > Basic information`.
* Changed `src/Storefront/Resources/views/storefront/layout/meta.html.twig` to use the `metaAuthor` configuration.
