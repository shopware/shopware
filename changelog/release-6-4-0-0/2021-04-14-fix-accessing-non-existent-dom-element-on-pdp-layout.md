---
title: fix accessing non existent dom element on pdp layout
issue: NEXT-14740
---
# Storefront
*  Changed `id`, `aria-labelledby`, `href`, `aria-controls` attributes in `storefront/element/cms-element-cross-selling.html.twig` to set DOM element id attribute to corresponding tab panel
*  Changed `descriptionTabHref`, `descriptionTabContent`, `reviewTabHref`, `reviewTabContent` in `storefront/element/cms-element-product-description-reviews.html.twig` to set DOM element id attribute to corresponding tab panel
*  Changed `reviewTabHref` in `storefront/component/buy-widget/buy-widget.html.twig` to set correct href attribute for Review tab
