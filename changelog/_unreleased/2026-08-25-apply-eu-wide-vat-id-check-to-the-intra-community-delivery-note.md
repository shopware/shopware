---
title: Apply the EU-wide VAT ID check to the intra-community delivery note
issue: 12438
---
# Core
* Changed `Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer::isValidVat()` and `Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider` to validate the order's VAT IDs with the shared `CustomerVatIdentification` constraint, so both document stacks accept a VAT ID of any EU member state.
___
# Upgrade Information
## Documents agree with the cart on the intra-community delivery note
The invoice, the cancellation invoice and the credit note now print the intra-community delivery note for the same orders that the cart treats as tax free.
No public method signatures changed, so renderers and document data providers extending them keep working.
