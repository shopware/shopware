---
title: Improve media search to include sub folders
issue: NEXT-11048
---
# Core
* Added field `path` to `Shopware/Core/Content/Media/Aggregate/MediaFolder/MediaFolderDefinition.php` to store the whole path.
* Added `path` to `Shopware/Core/Content/Media/Aggregate/MediaFolder/MediaFolderEntity.php` to store the whole path.
* Changed `Shopware/Core/Content/Media/DataAbstractionLayer/MediaFolderIndexer.php` to set the correct path to the media folder.
* Changed `Shopware/Core/Framework/DataAbstractionLayer/Indexing/TreeUpdater.php` to be able to update entities without the field `version_id`.
___
# Administration
* Changed `Shopware/Administration/Resources/app/administration/src/module/sw-media/component/sw-media-library/index.js` to be able to also search in all subFolders.
