---
title: Add dynamic positioning on link flyout, when sw-text-editor is used within sw-modal
issue: NEXT-20977
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Changed `positionLinkMenu` method in `sw-text-editor-toolbar-button/index.js` to expect other containers, that could limit the width and cut off the popup
* Added `getFlyoutMenuContainerElement` method to `sw-text-editor-toolbar-button/index.js` to evaluate the closest container that cuts off a popup container
