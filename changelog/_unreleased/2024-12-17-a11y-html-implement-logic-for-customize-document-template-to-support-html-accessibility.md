---
title: [A11y-HTML] Implement Logic for customize Document Template to Support HTML Accessibility
issue: NEXT-40063
---
# Core
* Changed some files to call `RenderedDocument::setHtmlA11y` to set the HTML accessibility content for the rendered document.
  * `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render`
  * `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render`
* Added parameter `htmlA11y` in `Shopware\Core\Checkout\Document\Renderer\RenderedDocument` to the provide The HTML accessibility content for the rendered document.
* Changed `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute::download` to add the `fileType` configuration to the `DocumentGenerator`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::readDocument` to load the media based on `fileType`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::generate` to save `documentMediaFileIds`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::preview` to set the content based on `fileType`.
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
