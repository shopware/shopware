---
title: Fix layout_head_javascript_tracking block on checkout confirm
issue: NEXT-9215
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Storefront
* Removed overwrite of `{% block base_head %}` from `storefront/page/checkout/_page.html.twig` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`
* Deprecated `src/Storefront/Resources/views/storefront/page/checkout/confirm/meta.html.twig`
