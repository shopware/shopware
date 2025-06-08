---
title: Fix clearance of sw-text-editor
issue: NEXT-11338
author: Thorben Pantring
author_email: t.panting@shopware.com 
author_github: pantrtxp
---
___
# Administration
* Changed `value` watch handler of `sw-text-editor`. The innerHTML will now be cleared if it's value does not match the innerHTML
