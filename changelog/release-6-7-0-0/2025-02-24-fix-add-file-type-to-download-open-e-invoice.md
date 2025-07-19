---
title: Add file type to download and open e-invoices
issue: https://github.com/shopware/shopware/issues/6969
author_github: @En0Ma1259
---
# Core
* Added check `companyCountry` key inside `mergeConfiguration` for `Shopware\Core\Checkout\Document\DocumentConfigurationFactory` 
___
# Administration
* Added `fileExtension` to `onDownload` and `onOpenDocument` method in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-document-card/sw-order-document-card.html.twig` 
