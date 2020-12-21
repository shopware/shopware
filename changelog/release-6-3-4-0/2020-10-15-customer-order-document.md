---
title:  Customer order document
issue: NEXT-10975
---
# Administration
* Added new bool field `displayInCustomerAccount` to data prop `generalFormFields` in `module/sw-settings-document/page/sw-settings-document-detail/index.js`
___
# Core
* Added `documents.documentType` association to `Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader.php`
* Added `documents.documentType` association to `Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader.php`
* Added `EqualsFilter` to `Shopware\Core\Checkout\Order\SalesChannel\OrderRoute.php`. Order documents will be filter by `sent` status and `displayInCustomerAccount` configuration.
___
# Storefront
* Added block `page_account_order_documents_table` to the `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail.html.twig` template
* Added 2 new templates `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document.html.twig` and `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig`
