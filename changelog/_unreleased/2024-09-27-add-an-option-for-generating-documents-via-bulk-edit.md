---
title: Add an option for generating documents via bulk edit
issue: NEXT-38341
flag: V6_7_0_0
---
# Core
* Changed `Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory::create` to add a `documents.documentType` association.
* Changed the following methods to check if the document exists before rendering.
  * `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render`
___
# Administration
* Added block `sw_bulk_edit_order_documents_skip_generate_invoice` in `src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents-generate-invoice/sw-bulk-edit-order-documents-generate-invoice.html.twig` to display the option for generating invoices through bulk edit. The logic has changed, and by default, documents will no longer be created if they already exist. The new toggle allows users to force the old behavior, enabling document creation even when documents are already present.
