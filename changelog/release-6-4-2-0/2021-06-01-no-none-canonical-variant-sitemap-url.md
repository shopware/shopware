---
title: No none canonical URL in sitemap for variants
issue: NEXT-15585
author: Sebastian Diez
author_email: s.diez@seidemann-web.com
author_github: @s-diez
---
# Core
* Added test for generation of product variant urls for `ProductUrlProvider`
* Added test for generation of product variant urls with canonical variant for `ProductUrlProvider`
* Changed the `ProductUrlProvider` to only generate one sitemap entry for the canonical product for variants with a canonical product
