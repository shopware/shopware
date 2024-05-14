---
title: Revert NEXT-29109 - Bad performance when editing text with many CMS blocks
issue: NEXT-30036
---
# Administration
* Removed `content` data prop and `handleUpdateContent` in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/index.js` and `/Users/tuan/Workspaces/platform/src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig`
* Removed `ref` attribute "elementComponentRef" for `component` tag in `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-slot/sw-cms-slot.html.twig`
