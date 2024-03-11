---
title: fix media delete pagination
issue: NEXT-31545
---
# Core
* Changed `media:delete-unused` to correctly paginate based on deleted media
* Changed `media:delete-unused` to include a progress bar with elapsed, ETA & mem usage
