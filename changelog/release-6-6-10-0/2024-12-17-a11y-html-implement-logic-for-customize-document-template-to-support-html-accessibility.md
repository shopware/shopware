---
title: [A11y-HTML] Offer HTML alternative to our pdf standard documents
issue: NEXT-40059
---
# Core
* Changed `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render`
  * to `setTemplate` for `RenderedDocument`
  * to `setContext` for `RenderedDocument`
  * to `setOrder` for `RenderedDocument`
* Changed `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render`
  * to `setTemplate` for `RenderedDocument`
  * to `setContext` for `RenderedDocument`
  * to `setOrder` for `RenderedDocument`
* Changed `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render`
  * to `setTemplate` for `RenderedDocument`
  * to `setContext` for `RenderedDocument`
  * to `setOrder` for `RenderedDocument`
* Changed `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render`
  * to `setTemplate` for `RenderedDocument`
  * to `setContext` for `RenderedDocument`
  * to `setOrder` for `RenderedDocument`
* Added parameters `template`, `context`, `order` in `Shopware\Core\Checkout\Document\Renderer\RenderedDocument`.
* Changed `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute::download` to implement authenticate for customer.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::readDocument` to load the media based on `fileType`.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::generate` to save `documentA11yMediaFileId` field.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentGenerator::preview` to set the content based on `fileType`.
* Added `Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry` to callable render by contentType.
* Added `Shopware\Core\Checkout\Document\Service\HtmlRenderer` to render the document file.
* Changed `Shopware\Core\Checkout\Document\Service\PdfRenderer` to use `documentTemplateRenderer` render the document.
* Changed `Shopware\Core\Checkout\Document\Controller\DocumentController::downloadDocument` to add the `fileType` configuration to the `DocumentGenerator`.
* Changed `src/Core/Framework/Resources/views/documents/base.html.twig` to implement accessibility for HTML documents.
* Added `Shopware\Core\Framework\Event\A11yRenderedDocumentAware` to provide the document ids to render the A11y document.
* Added `Shopware\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer` to store the document ids and documents to render the A11y documents.
* Changed `Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent` to implements `A11yRenderedDocumentAware`
___
# Administration
* Changed method `getDocumentPreview` in `document.api.service` service to add the `fileType` like <html or pdf> attributes.
* Changed `sw-order-document-card`.
  * `onPreview` method to add new `fileType` attribute.
  * `openDocument` method to add new `fileType` attribute.
  * `downloadDocument` method to add new `fileType` attribute.
  * `getDocumentColumns` computed to add new column `fileTypes`.
* Added method `availableFormatsFilter` in `sw-order-document-card` component to filter the available formats.
* Added block `sw_order_document_card_grid_column_avaiable_formats` in `sw-order-document-card.html.twig` to show the available formats column.
* Changed method `onPreview` in `sw-order-document-settings-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-credit-note-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-delivery-note-modal` component to add new `fileType` attribute.
* Changed method `onPreview` in `sw-order-document-settings-invoice-modal` component to add new `fileType` attribute.
* Added block `sw_order_document_settings_modal_form_document_footer_preview` in `sw-order-document-settings-modal.html.twig` to show the preview HTML button.
* Added method `loadTheLinksForA11y` in `sw-order-send-document-modal` component to load the HTML links for a11y.
* Changed method `onSendDocument` in `sw-order-send-document-modal` component to add `a11yDocuments` attribute for `mailService.sendMailTemplate`.
* Changed data `generalFormFields` in `sw-settings-document-detail` component to add `config` `fileTypes` to show formats like <pdf, html>.
* Added method `onRemoveDocumentType` in `sw-settings-document-detail` component to remove item with `sw-multi-select` component.
* Added method `onAddDocumentType` in `sw-settings-document-detail` component to add item with `sw-multi-select` component.
* Added block `sw_settings_document_detail_content_form_field_renderer_multi_select` in `sw-settings-document-detail.html.twig` to show the multi select component.
___
# Storefront
* Changed block `page_account_order_document_item_detail_file_name` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig` to add the link to render `html` document.
