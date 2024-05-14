---
title: Fix vue-meta for Vue 3
issue: NEXT-30501
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `meta-info.plugin.js` instead of `vue-meta` for Vue 3
___
# Next Major Version Changes
## Removal of vue-meta:
* `vue-meta` will be removed. We use our own implementation which only supports the `title` inside `metaInfo`.
* If you use other properties than title they will no longer work.
* If your `metaInfo` option is a object, rewrite it to a function returning an object.
