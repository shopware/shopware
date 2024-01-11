---
title: Trigger MediaIndexer after MediaPathPostUpdater
issue: NEXT-32919
---
# Core
* Changed `MediaPathPostUpdater` to trigger `MediaIndexer` after updating the media path, so the denormalized Thumbnail structs in the media table are updated as well with the newly generated paths.
