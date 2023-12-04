---
title: Stop fetching media items individually
issue: NEXT-29586
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: bschulzebaek
---
# Administration
* Changed the component `sw-media-media-item` to pass the media entity to its child component `sw-media-preview-v2` instead of only the id. This way, listings of media items (e.g. the media module) won't fetch items individually anymore, heavily reducing the number of requests.
