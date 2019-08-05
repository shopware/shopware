[titleEn]: <>(Added MediaItem association to DocumentEntity)

The `DocumentEntity` has been refactored. An association with `MediaItem` to the property `documentMediaFile` has been added.

This association is used to store an already generated document-file to the document-entry.  
A document will be generated once and stored as a **private** `MediaItem`. Following calls for document generation will not regenerate the file, but load the already generated file.
The `DocumentService::preview()`-Method will still generate the document on the fly.