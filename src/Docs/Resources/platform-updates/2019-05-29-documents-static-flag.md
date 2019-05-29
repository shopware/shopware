[titleEn]: <>(Added static Property to DocumentEntity)

The `DocumentEntity` has been refactored. A boolean property named `static` has been added.

This property is used to determine wether a document can be generated or not. If `static` is set to true. The `DocumentEntity` is marked as linked to a static file which can not regenerated.
The file is stored in the `documentMediaFile` association of the `DocumentEntity`.
The main purpose for the `static` property is to provide a way to store documents of legacy shopware versions or third-party systems to the new document structure and asure these files will stay untouched by any regeneration of documents.

When calling the `DocumentService::getDocument()`-Method even with the parameter `$regenerate` set to `true` these documents won't be regenerated or changed in any way.

Now you can import legacy documents to the system via the following way:

* Add a new MediaItem with the `private`-property set to `true`
```php
$newMediaId = Uuid::randomHex();
$mediaRepository->create(
    [
        'id' => $newMediaId,
        'private' => true
    ],
);
```
* Save the file to the server via the `MediaService`
```php
$mediaService->saveFile(
    $fileBlob,      // Blob of file to save
    $fileExtension, // Extension of the file (eg. 'pdf')
    $contentType,   // ContentType of the file (eg. 'application/pdf')
    $filename,      // Filename fof the file (eg. 'invoice_23_12_1977_123')
    $context,       // Context
    'document',     // MediaFolder to save the new file (default for documents is 'document')
    $newMediaId     // Id of the MediaItem created as "private")
);
```
* Create DocumentEntity as `static` with associating MediaId
```php
$documentRepository->create([
        [
            'id' => Uuid::randomHex(),
            'documentTypeId' => $documentType->getId(), // (id of type ('invoice', 'storno',...))
            'fileType' => FileTypes::PDF,
            'orderId' => $orderId, // Id of the order this document is associated with.
            'orderVersionId' => $orderVersionId, // VersionId of the order this document is associated with.
            'config' => ['documentNumber' => '1001'], // documentConfig with at least the documentNumber
            'deepLinkCode' => 'xyz123', // Deeplinkcode 
            'static' => true,   // static = true is important to prevent overwriting of the document 
            'documentMediaFile' => [
                'id' => $newMediaId,
            ],
        ],
    ],
    $this->context
);
```
It is impotant that you need an already imported order to associate the document with. Currently every document needs a valid orderId to be associated with.
 