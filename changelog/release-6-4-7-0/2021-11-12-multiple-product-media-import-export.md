---
title: Multiple product media import export
issue: NEXT-14709
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `hash` property and getter in `Shopware\Core\Content\Media\File\MediaFile` for storing a file hash.
* Changed `Shopware\Core\Content\Media\File\FileFetcher` to hash file and store it in instance of `MediaFile`.
* Changed `Shopware\Core\Content\Media\Metadata` to add file hash to metadata from instance of `MediaFile`.
* Changed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity::deserialize` to use file hash for fetching existing `media` data sets linked to an identical file, unless a specific `id` is provided.  
* Changed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer::deserialize` to read URLs in the format of `http://example.com/example1.png|http://example.com/example2.png` from `media` key and deserialize them using `MediaSerializer`.
* Changed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer::serialize` to write URLs in the format of `http://example.com/example1.png|http://example.com/example2.png` to mapped `media` key from existing product media.
___
# Administration
* Changed `sw-import-export-entity-path-select` to handle one-to-many `media` assocation in mapping
