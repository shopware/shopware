---
title: Fix media module inline edit handler
issue: NEXT-32926
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: bschulzebaek
---
# Administration
* Changed `sw-media-folder-item` inline edit `@change` handler to be called by the `@blur` handler internally instead, since the change event was removed from `sw-text-field`
* Changed `sw-media-media-item` inline edit `@change` handler to be called by the `@blur` handler internally instead, since the change event was removed from `sw-text-field`
