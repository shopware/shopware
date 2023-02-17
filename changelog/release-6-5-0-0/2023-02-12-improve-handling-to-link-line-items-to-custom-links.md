---
title: Improve handling to link line items to custom links
issue: NEXT-25438
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Added the possibility to link line items to custom links in `storefront/component/line-item/element/image.html.twig` and `storefront/component/line-item/element/label.html.twig` using the variables `lineItemLink` and `lineItemModalLink`.
* Deprecated boolean variable `productLink` in templates `storefront/component/line-item/element/image.html.twig` and `storefront/component/line-item/element/label.html.twig`. Use `lineItemLink` and `lineItemModalLink` instead and pass the desired url as string.
* Changed selector `[data-toggle="modal"]` to `[data-ajax-modal="modal"]` in `storefront/component/line-item/element/image.html.twig` and `storefront/component/line-item/element/label.html.twig` to be compatible with Bootstrap 5 AJAX modals for showing items on the confirm page.
