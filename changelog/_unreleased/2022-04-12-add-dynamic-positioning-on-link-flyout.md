---
title: Add dynamic positioning on link flyout
issue: NEXT-20977
author: Luka Brlek
author_email: l.brlek@shopware.com
---
# Administration
* Added `dynamicPositionStyle` as inline styling for `sw_text_editor_toolbar_button_link_menu` in `/sw-text-editor-toolbar-button/sw-text-editor-toolbar-button.html.twig` to position the link flyout dynamicaly back inside the viewport.
* Added `getLinkMenuPosition` method in `/sw-text-editor-toolbar-button/index.js` that calculates position of the link flyout.
* Added `--arrow-position` as CSS variable in `/sw-text-editor-toolbar-button/sw-text-editor-toolbar-button.scss` for the `sw-text-editor-toolbar-button__children:before` element to be positioned right below the toolbar link icon. 