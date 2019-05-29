[titleEn]: <>(Added MediaItem association to DocumentEntity)

The `DocumentEntity` has been refactored. An association with `MediaItem` to the property `documentMediaFile` has been added.
Also a new system setting named `core.saveDocuments` was added and defaults to true.

This association is used to store an already generated document-file to the document-entry.  
If the system setting `core.saveDocuments` is set to true, a document will be generated once and stored as a **private** `MediaItem`. Following calls for document generation will not regenerate the file, but load the already generated file.
The regeneration process can be enforced with a `$regenerate` parameter set to `true` for the `DocumentService::getDocument()`-Method 