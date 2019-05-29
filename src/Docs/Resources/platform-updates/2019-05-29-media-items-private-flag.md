[titleEn]: <>(Added private flag for MediaItems)

The `MediaItems` and `MediaFolderConfiguration` have been refactored. The property `private` has been added as a boolean field.

The `private-flag` provides a way to mark a folder or a `MediaItem` as **private**.
**Private** `MediaItems` will be stored in via the filesystem.private instead of filesystem.public and therefor not accessible via web.
**Private** `MediaItems` are not shown in the MediaItems-Admin by now. They are excluded from any API-Call of the Media-Entity.
To get access to those **Private** `MediaItems` the Repository-Request has to be made with a `SYSTEM_SCOPE`-Context.  

Example of a `SYSTEM_SCOPE`-Context:
```php
$mediaService = $this->mediaService;
$context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaService, $document, &$fileBlob) {
    $fileBlob = $mediaService->loadFile($document->getDocumentMediaFileId(), $context);
});
$generatedDocument->setFileBlob($fileBlob);
```