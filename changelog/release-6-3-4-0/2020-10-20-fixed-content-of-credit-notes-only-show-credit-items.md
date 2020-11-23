---
title: Fixed content of credit notes only show credit items
issue: NEXT-10687
---
# Administration
* Added method `setTotalPrice` in `src/Core/Checkout/Cart/Price/Struct/CalculatedPrice.php` to set total price for CalculatedPrice item .
* Added method `getPrices` in `src/Core/Checkout/Order/Aggregate/OrderLineItem/OrderLineItemCollection.php` to get price of each order line item .
* Added override of block `document_line_item_table_iterator` in template `src/Core/Framework/Resources/views/documents/credit_note.html.twig` which extends from `src/Core/Framework/Resources/views/documents/base.html.twig`.
* Added override of block `document_line_item_table_shipping` in template `src/Core/Framework/Resources/views/documents/credit_note.html.twig` which extends from `src/Core/Framework/Resources/views/documents/base.html.twig`.
* Added override of block `document_sum` in template `src/Core/Framework/Resources/views/documents/credit_note.html.twig` which extends from `src/Core/Framework/Resources/views/documents/base.html.twig`.
* Changed config document from `%deliveryNoteNumber%` to `%creditNoteNumber%` to correct the number of credit note.
