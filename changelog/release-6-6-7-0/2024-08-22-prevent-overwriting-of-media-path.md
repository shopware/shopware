---
title: Prevent overwriting of media path
issue: NEXT-38012
---
# Core
* Changed `\Shopware\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater::handle` to prevent overwriting already existing media paths, and thus fixing an issue that the path might not match the physical location anymore, when the media file was renamed after the initial upload.
