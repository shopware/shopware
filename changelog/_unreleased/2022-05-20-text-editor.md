---
title: sw-text-editor shift paste without format
issue: NEXT-20708
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added the method `keyListener` and the data property `isShiftPressed` in `sw-text-editor/index.js` to keep track of whether the `SHIFT` key is pressed
* Changed the method `onPaste` in `sw-text-editor/index.js` to paste the plain text when `SHIFT` is pressed
