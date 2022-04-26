---
title: Rework Document Generator
issue: NEXT-17708
---
# Core
* Added new composer's dependencies `tecnickcom/tcpdf:6.4.4` and `setasign/fpdi:2.3.6` to merge generated documents
* Added a new service `Shopware\Core\Checkout\Document\Service\DocumentGenerator`
* Added new renderer classes in
    * `\Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer`
    * `\Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer`
    * `\Shopware\Core\Checkout\Document\Renderer\StornoRenderer`
    * `\Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer`
    * `\Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer`
* Deprecated `Shopware\Core\Checkout\Document\DocumentService`, use `Shopware\Core\Checkout\Document\Service\DocumentGenerator` instead
* Deprecated `\Shopware\Core\Checkout\Document\DocumentGeneratorController::createDocument`, use `createDocuments` instead
* Deprecated`\Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface` and its implementations use the `AbstractDocumentRenderer` instead
* Deprecated`\Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry` use the `DocumentRendererRegistry` instead
* Added new service `\Shopware\Core\Checkout\Document\Service\DocumentConfigLoader` to load document's config
* Added new service `\Shopware\Core\Checkout\Document\Service\DocumentMerger` to combine generated document into one pdf file
* Added new Struct class for Order Document in `Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation`
* Added new `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute` to provide store-api route for downloading generated document's content
___
# API
* Added new `POST` endpoint `/api/_action/order/document/{documentType}/create` in `\Shopware\Core\Checkout\Document\DocumentGeneratorController`. This endpoint is used for generating order documents in bulk
* Deprecated `POST` endpoint `/api/_action/order/{orderId}/document/{documentTypeName}` in `\Shopware\Core\Checkout\Document\DocumentGeneratorController`. Use `/api/_action/order/document/{documentType}/create` instead
* Added new `POST` endpoint `/api/_action/order/document/download` in `\Shopware\Core\Checkout\Document\DocumentGeneratorController`. This endpoint is used for merging order documents and downloading them in one pdf file
* Added new store-api route `/store-api/document/download/{documentId}/{deepLinkCode}` to download generated document
___
# Administration
* Changed method `createDocument` in `src/core/service/api/document.api.service.js` to use the new create documents endpoint
___
# Storefront
* Deprecated `Shopware\Storefront\Page\Account\Document\DocumentPageLoader` due to unused
* Changed `Shopware\Storefront\Controller\DocumentController` to use `Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute` to load document blob file
___
# Upgrade Information

## New bulk order's documents generator endpoint

* Endpoint and payload:
```
POST /api/_action/order/document/{documentType}/create
[
    {
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc98",
        "static": true,
        "config": {
            "documentComment": "Some comment",
            "documentNumber": "1002",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }, 
    {        
        "fileType": "pdf",
        "orderId": "012cd563cf8e4f0384eed93b5201cc99",
        "static": true,
        "config": {
            "documentComment": "Another comment",
            "documentNumber": "1003",
            "documentDate": "2021-12-13T00:00:00.000Z"
        }
    }
]
```

* Added new method `downloadBulkDocuments` in `Core/Checkout/Document/Controller/DocumentController.php` to allow downloading multiple documents

## New bulk order's documents downloading endpoint

* Endpoint and payload:
```
POST /api/_action/order/document/download
{
    "documentIds": [
        "012cd563cf8e4f0384eed93b5201cc98",
        "075fb241b769444bb72431f797fd5776",
    ],
}
```

## Store-Api
* Use `/store-api/document/download/{documentId}/{deepLinkCode}` route to download generated document of the given id
