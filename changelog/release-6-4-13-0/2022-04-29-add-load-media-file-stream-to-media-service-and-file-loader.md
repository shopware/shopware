---
title: Add loadMediaFileStream to MediaService and FileLoader
issue: NEXT-21711
author: JoshuaBehrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added `\Shopware\Core\Content\Media\File\FileLoader::loadMediaFileStream` as companion to `\Shopware\Core\Content\Media\File\FileLoader::loadMediaFile` but it returns a stream for better memory management options with big files
* Added `\Shopware\Core\Content\Media\MediaService::loadFileStream` as companion to `\Shopware\Core\Content\Media\MediaService::loadFile` but it returns a stream for better memory management options with big files
