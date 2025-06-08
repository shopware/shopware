---
title: Fix media module for Vue 3
issue: NEXT-29001
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-media-index` to set term to empty string when the media folder is changed
* Changed `sw-media-index` reload to correctly display the media folder
* Added `@keydown.enter.stop` handler to `sw-text-field` in `sw-media-folder-item`
* Added `name` to `sw-media-url-form` textfields
