---
title: Fix cms data mapping for nested translations
issue: NEXT-36499
author: Christoph Pötz
author_email: christoph.poetz@acris.at
author_github: acris-cp
---
# Storefront
* Changed the `resolveEntityValue` method in `AbstractCmsElementResolver` to read the translated values of nested entities in cms data mapping
