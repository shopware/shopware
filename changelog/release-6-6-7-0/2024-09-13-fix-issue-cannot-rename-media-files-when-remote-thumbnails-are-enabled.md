---
title: Fix issue cannot rename media files when remote thumbnails are enabled
issue: NEXT-38309
---
# Core
* Changed `renameMedia` method in `src/Core/Content/Media/File/FileSaver.php` to prevent thumbnail renaming when remote thumbnails are enabled.
