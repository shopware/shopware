---
title: Apply domain exception for media
issue: NEXT-26928
---
# Core
* Added new domain exception class `Shopware\Core\Content\Media\MediaException`.
* Deprecated the following exceptions in replacement for Domain Exceptions:
  * `Shopware\Core\Content\Media\Exception\CouldNotRenameFileException`
  * `Shopware\Core\Content\Media\Exception\DisabledUrlUploadFeatureException`
  * `Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException`
  * `Shopware\Core\Content\Media\Exception\EmptyMediaIdException`
  * `Shopware\Core\Content\Media\Exception\FileExtensionNotSupportedException`
  * `Shopware\Core\Content\Media\Exception\IllegalFileNameException`
  * `Shopware\Core\Content\Media\Exception\IllegalUrlException`
  * `Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException`
  * `Shopware\Core\Content\Media\Exception\MissingFileExtensionException`
  * `Shopware\Core\Content\Media\Exception\StrategyNotFoundException`
  * `Shopware\Core\Content\Media\Exception\StreamNotReadableException`
  * `Shopware\Core\Content\Media\Exception\ThumbnailCouldNotBeSavedException`
  * `Shopware\Core\Content\Media\Exception\ThumbnailNotSupportedException`
  * `Shopware\Core\Content\Media\Exception\UploadException`
