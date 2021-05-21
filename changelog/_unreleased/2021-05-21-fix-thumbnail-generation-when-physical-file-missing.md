---
title: Fix thumbnail generation when physical file is missing
issue: NEXT-7754
author: David Fecke
author_github: @leptoquark1
---
# Core
* Fix `updateThumbnails` function in `src/Core/Content/Media/Thumbnail/ThumbnailService.php` so that the physical presence of the thumbnail file is respected
