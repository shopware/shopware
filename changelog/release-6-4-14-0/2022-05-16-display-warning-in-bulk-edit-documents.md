---
title: Display warning in bulk edit documents
issue: NEXT-21620
---
# API
* Added new endpoint `api/_action/document/extending-deprecated-service` to check if there is any external services extending from deprecated documents services
___
# Administration
* Added a new method `extendingDeprecatedService` in `OrderDocumentApiService`
* Added a new data property `showBulkEditDocumentWarning` in `sw-bulk-edit-order` component to check if we should show bulk edit documents warning
* Added a new block `sw_bulk_edit_order_content_documents_warning` in `sw-bulk-edit-order.html.twig` to show bulk edit documents warning
