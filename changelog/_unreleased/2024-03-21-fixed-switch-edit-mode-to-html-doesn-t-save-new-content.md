---
title: Fixed switch edit mode to html doesn't save new content
issue: NEXT-32927
---
# Administration
* Removed passing `sanitizer-input` prop to `sw-text-editor` component. This prop was used to enable the HTML sanitizer in the `sw-text-editor` component in `src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig`
