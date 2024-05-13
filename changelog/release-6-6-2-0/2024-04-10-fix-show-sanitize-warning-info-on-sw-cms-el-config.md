---
title: Fix show sanitize warning info on sw-cms-el-config
issue: NEXT-32927
---
# Administration
* Added a new prop `sanitizeInfoWarn` in the `sw-code-editor` component which is default to false
* Added a new prop `sanitizeInfoWarn` in the `sw-text-editor` component  which is default to false
* Changed the template of `src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig` to pass the `sanitizeInfoWarn` prop as true to the `sw-code-editor` component 
