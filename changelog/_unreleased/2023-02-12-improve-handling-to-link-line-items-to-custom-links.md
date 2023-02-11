---
title: Improve handling to link line items to custom links
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Add the possibility to link line items to custom links in `storefront/component/line-item/element/image.html.twig` and `storefront/component/line-item/element/label.html.twig` using the variables `lineItemLink` and `lineItemModalLink`
* Deprecate boolean variable `productLink` from templates `storefront/component/line-item/element/image.html.twig` and `storefront/component/line-item/element/label.html.twig`
* Use the new Bootstrap 5 AJAX modals for showing items on the confirm page
