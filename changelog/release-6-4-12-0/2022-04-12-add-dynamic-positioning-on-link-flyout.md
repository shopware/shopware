---
title: Add dynamic positioning on link flyout
issue: NEXT-20977
author: Luka Brlek
author_email: l.brlek@shopware.com
---
# Administration
* Added `--flyoutLinkLeftOffset` for `.sw-text-editor-toolbar-button__children` in `sw-text-editor-toolbar-button.html.twig` to position the link flyout dynamically back inside the viewport.
* Added `--arrow-position` in `/sw-text-editor-toolbar-button/sw-text-editor-toolbar-button.scss` for the `sw-text-editor-toolbar-button__children:before` element to be positioned right below the toolbar link icon.
* Added `positionLinkMenu` method in `/sw-text-editor-toolbar-button/index.js` that calculates the `--flyoutLinkLeftOffset` and the `--arrow-position` css variables on resize.
