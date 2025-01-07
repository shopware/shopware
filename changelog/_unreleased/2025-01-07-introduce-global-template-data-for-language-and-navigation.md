---
title: Introduce global template data for language and navigation
issue: NEXT-39744
author: Michael Telgmann
author_github: @mitelg
---

# Storefront
* Deprecated the usage of the `header` and `footer` properties of page Twig objects outside the dedicated header and footer templates. Use the following alternatives instead:
    * `context.currency` instead of `page.header.activeCurrency`
