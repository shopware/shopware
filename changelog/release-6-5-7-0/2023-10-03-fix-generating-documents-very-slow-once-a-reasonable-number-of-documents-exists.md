---
title: Fix Generating documents very slow once a reasonable number of documents exists
issue: NEXT-29060
---
# Core
* Added migration `Shopware\Core\Migration\V6_5\Migration1696300511AddDocumentNumberToDocumentEntity` to add column `document_number`
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::preview` to use `document_number` instead of `config.documentNumber` to generate preview documents.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::generate` to add `documentNumber` to write records.
* Changed `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render` to use `document_number` instead of `config.documentNumber` to generate credit note documents.
* Changed `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render` to use `document_number` instead of `config.documentNumber` to generate delivery note documents.
* Changed `Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader::load` to get `document.document_number`.
