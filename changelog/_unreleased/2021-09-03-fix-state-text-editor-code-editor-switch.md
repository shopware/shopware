---
title:              Fix placeholderVisible state depending on isEmpty on mode switch
issue:              NEXT-17045
author:             Wolfgang Kreminger
author_email:       r4pt0s@protonmail.com
author_github:      @r4pt0s
---
# Administration
- Changed `onContentChange` method in `platform/src/Administration/Resources/app/administration/src/app/component/form/sw-text-editor/index.js` to fix state update of placeHolderVisible
- Changed `emitHtmlContent` method in `platform/src/Administration/Resources/app/administration/src/app/component/form/sw-text-editor/index.js` to fix state update of placeHolderVisible on switching from code to text editor mode
