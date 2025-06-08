---
title: Media thumbnails load incorrectly when switching remote thumbnail setting from disabled to enabled
issue: NEXT-37370
---
# Core
* Changed `load` method in `src/Core/Content/Media/Core/Application/RemoteThumbnailLoader.php` to set thumbnails to an empty collection if the remote thumbnail sizes are empty, to prevent loading of outdated thumbnails.
* Added `src/Core/Content/Media/Commands/DeleteThumbnailsCommand.php` command to delete unused thumbnails when remote thumbnails is enabled.
