---
title: WYSIWYG copying and fix text-editor toolbar position
issue: NEXT-18745
author: Niklas Limberg
author_github: NiklasLimberg
author_email: n.limberg@shopware.com
---
# Administration
* Changed `sw-text-editor-toolbar/index.js` to update the toolbar position on text alignment changes, scrolling and resizing
* Changed `sw-text-editor/index.js` to allow copy and paste of html in editor mode
* Added a `copy` event listener to the `.sw-text-editor__content-editor` element in `sw-text-editor.html.twig` 
* Changed style of `.sw-cms-toolbar` in `sw-cms-toolbar.scss`, to let the text-editor-toolbar slide under the `cms-toolbar`
* Changed `sw-text-editor/index.js` to fix text alignment assignment, when a complete HTML element is selected