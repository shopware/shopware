---
title: Removed write protection flag from fileExtension field in the MediaDefinition
issue: #7993
author: Martin Krzykawski
author_github: @MartinKrzykawski
---
# Core
* Removed write protection flag from `fileExtension` field in the `Shopware\Core\Content\Media\MediaDefinition`. 
* Changed `Shopware\Core\Content\Media\MediaDefinition` that media entities can now be created via the Admin API, allowing the file extension to be set freely.
