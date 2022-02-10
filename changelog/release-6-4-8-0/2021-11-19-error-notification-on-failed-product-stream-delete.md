---
title: Error notification on failed product stream delete
issue: NEXT-15249
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Added `onDeleteItemFailed` method in `sw-product-stream-list` to create error notifications when product stream deletion fails due to existing category associations
