---
title: Fix CMS block component rendering
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `sw-cms-section` component to render CMS block components by using their component name from block config. `sw-cms-block-${block.type}` is used as a fallback if no component name is defined.
___
# Upgrade Information
## CMS block component name will be used
When rendering CMS block components in the Administration, the `component` property of the block config will be used instead of `sw-cms-block-${block.type}`. If there is no component name defined, `sw-cms-block-${block.type}` will be used as a fallback. Make sure you have set the correct component name in your CMS block configs.
