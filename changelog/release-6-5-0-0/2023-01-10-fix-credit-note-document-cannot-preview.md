---
title: Fix credit note renderer wrong logic
issue: NEXT-25492
---
# Core
* Changed function `render` in `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer` to set default invoice.
* Changed function `render` in `Shopware\Core\Checkout\Document\Renderer\StornoRenderer` to set default invoice.
* Changed `Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader::load ` to add conditions to checking invoices with the existing order.
