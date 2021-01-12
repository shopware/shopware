---
title: Documents receive the same document number
issue: NEXT-12947
---
# Core
* Added `checkDocumentNumberAlreadyExits` in `src/Core/Checkout/Document/DocumentService.php` method to check document number already exists or not.
* Added `DocumentNumberAlreadyExistsException` exception to show the message for document number already exists
___
# Administration
* Added `DocumentEvents` and `$listener` to support to show the status of fail or finished of create document
* Added notification in `sw-order-document-card` to show the messages from `listener`
