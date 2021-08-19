---
title: WYSIWYG Editor Inline toolbar modal closes on click
issue: NEXT-16763
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
*  Changed the `onSelectionChange` method in `sw-text-editor/index.js`, to not clear the selection if a button in the toolbar is pressed.
