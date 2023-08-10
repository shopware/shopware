---
title: Bad performance when editing text with many CMS blocks
issue: NEXT-29109
---
# Administration
* Changed the method `onCloseSettingsModal` in `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-slot/index.js`
* Changed the method `emitChanges` and added a data prop `content` and a method `handleUpdateContent` in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/index.js`
