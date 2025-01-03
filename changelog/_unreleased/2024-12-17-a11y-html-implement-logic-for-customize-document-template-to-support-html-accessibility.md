---
title: [A11y-HTML] Offer HTML alternative to our pdf standard documents
issue: NEXT-40059
---
# Core
* Changed some files to call `RenderedDocument::setTemplateOptions` to provide template options to render document template.
  * `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render`
* Added parameter `templateOptions` in `Shopware\Core\Checkout\Document\Renderer\RenderedDocument` to the provide the config to render template.
* Changed `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute::download` to add the `fileType` configuration to the `DocumentGenerator`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::readDocument` to load the media based on `fileType`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::generate` to save `documentMediaFileIds`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::preview` to set the content based on `fileType`.
* Added `Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry` to callable render by contentType.
* Added `Shopware\Core\Checkout\Document\Service\HtmlRenderer` to render the document file.
* Changed `Shopware\Core\Checkout\Document\Service\PdfRenderer` to add the function `templateRenderer` to add document template. 
* Changed `Shopware\Core\Checkout\Document\Controller\DocumentController::downloadDocuments` to add the attribute `fileType` to the request.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentMerger::merge` to implement a case zip archive to merge documents.
___
# Administration
* Changed method `getDocumentPreview` in `document.api.service` service to add the `fileType` like <html or pdf> attributes.
* Changed method `onPreview` in `sw-order-document-card` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-credit-note-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-delivery-note-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-invoice-modal` component to add new `fileType` attribute.
___
# Storefront
* Changed block `page_account_order_document_item_detail_file_name` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig` to add the link to render `html` document.
