---
title: Show shipping costs in next page if items per page is reached
issue: NEXT-22931
---
# Core
* Deprecated methods `getPage` and `incrementPage` in `Shopware\Core\Checkout\Document\DocumentGenerator\Counter` due to unused
* Deprecated variable `pages` in `src/Core/Framework/Resources/views/documents/base.html.twig` due to unused
* Changed template `src/Core/Framework/Resources/views/documents/includes/loop.html.twig` to render `shipping_costs` in separated page if end page is reached
