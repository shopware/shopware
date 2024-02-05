---
title: Refactor pagination templates and paging of the search result
issue: NEXT-32337
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed return type of `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult::getPage` from `?int` to `int` as the page is always computed
___
# Storefront
* Deprecated `Shopware\Storefront\Framework\Page\StorefrontSearchResult` for Shopware v6.7.0
* Changed template `@Storefront/storefront/component/pagination.html.twig` to use the already computed values of the `EntitySearchResult` and remove the variable `totalEntities` for the next major version v6.7.0, use `totalPages` instead, if you want to specify a custom current page you can now pass the variable `currentPage`
* Changed template `@Storefront/storefront/component/product/listing.html.twig` to use the already computed value for `currentPage` of the `searchResult` and adapt to changes for the pagination for the next major version v6.7.0
* Changed `@Storefront/storefront/component/review/review.html.twig` and use variable for the `reviewsPerPage` instead of a magic number and adapt to changes for the pagination for the next major version v6.7.0
* Changed `@Storefront/storefront/page/product-detail/review/review.html.twig` to adapt to changes for the pagination for the next major version v6.7.0
* Changed `@Storefront/storefront/page/account/order-history/index.html.twig` to use the already computed value of the `totalPages` of the orders search result and adapt to changes for the pagination for the next major version v6.7.0
___
# Next major version changes
## Core
### Removal of `StorefrontSearchResult`
The class `Shopware\Storefront\Framework\Page\StorefrontSearchResult` will be removed without replacement, since all functionality should be contained in the parent class `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult`. The sorting was not in active use in Shopware, so if that is needed it should be added as extension to the `EntitySearchResult`.
## HTML/Twig:
### Removal of the `total` variable from `pagination.html.twig`
* The template `@Storefront/storefront/component/pagination.html.twig` with a custom number of entries cannot be used with the `total` variable, instead pass `totalPages`. Additionally it is now possible to pass the variable `currentPage` to the pagination template. Furthermore the `criteria` variable cannot be used inside the `pagination.html.twig` template.
#### Before
```
{% sw_include '@Storefront/storefront/component/pagination.html.twig' with {
    entities: searchResult,
    criteria: searchResult.criteria,
    total: myCustomTotalNumber,
}  %}
```
#### After
```
{% sw_include '@Storefront/storefront/component/pagination.html.twig' with {
    entities: searchResult,
    totalPages: (myCustomTotalNumber / myCustomLimit)|round(0, 'ceil'),
}  %}
```
