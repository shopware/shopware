---
title: Delete links from the editor not possible 
issue: NEXT-17700
author: Niklas Limberg
author_email: n.limberg@shopware.com
---
# Administration
* Changed method `onButtonClick` in `sw-text-editor-toolbar/index.js` to allow the new button type `link-remove`
* Added the new class `sw-text-editor-toolbar-button__link-menu-buttons-button-remove` in `sw-text-editor-toolbar-button.html.twig`
* Added method `onRemoveLink` in `sw-text-editor/index.js` to implement the new link removal functionality
* Added handler for the new `removeLink` event emited in the `onButtonClick` method in `sw-text-editor.html.twig`
* Removed `sw-text-editor-toolbar.link.insert` snippet and replaced it with snippets from the `global.default` namespace