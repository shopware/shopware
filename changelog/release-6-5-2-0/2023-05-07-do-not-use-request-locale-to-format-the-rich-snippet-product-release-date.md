---
title: Do not use request locale to format the rich snippet product release date
issue: NEXT-26890
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `format_date` call in the twig templates `views/storefront/page/product-detail/buy-widget.html.twig` and
 `storefront/component/buy-widget/buy-widget.html.twig` to not consider the request locale, as there is an explicit format specified
