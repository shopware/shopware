---
title: Make line item conditions only consider goods where necessary
issue: NEXT-19576
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Changed `getFlat()` calls to `filterGoodsFlat()` in `LineItem*Rule` classes which should only consider goods
