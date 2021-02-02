---
title: Show additional information on invoice documents
issue: NEXT-11783
---
# Core
* Added new block `document_shipping_address` in `src/Core/Framework/Resources/views/documents/base.html.twig`
* Changed the blocks `document_footer_fourth_column`, `document_recipient`, `document_side_info`, `document_payment_shipping_inner` in `src/Core/Framework/Resources/views/documents/base.html.twig`
* Added new property `intraCommunityDelivery` to $config in `Core/Checkout/Document/DocumentGenerator/InvoiceGenerator`
* Added new associations `deliveries.shippingOrderAddress.country`, `orderCustomer.customer` in `Core/Checkout/Document/DocumentService`
___
# Administration
* Added new text field `companyPhone` to data prop `companyFormFields` in `module/sw-settings-document/page/sw-settings-document-detail/index.js`
* Added new method to get `companyFormFields` in `module/sw-settings-document/page/sw-settings-document-detail/index.js`
* Added new css to class `sw-settings-document-detail__company-address-checkbox` in `module/sw-settings-document/page/sw-settings-document-detail/sw-settings-document-detail.scss`
