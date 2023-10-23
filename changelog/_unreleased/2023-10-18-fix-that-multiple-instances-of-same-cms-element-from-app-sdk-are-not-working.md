---
title: Fix that multiple instances of same CMS element from app sdk are not working
issue: NEXT-25102
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added unique data key for CMS elements so that each single element can have unique configuration values. The value is accessible within the query parameter `elementId` from the iFrame
___
# Upgrade Information
## Use new key inside app CMS elements
change in app iFrame this
```js
data.subscribe(
    'your-cms-element-name__config-element',
    yourCallback,
{ selectors: yourSelectors });
```
to
```js
const elementId = new URLSearchParams(window.location.search).get('elementId');

data.subscribe(
    // add elementId to data key for identifying the correct element config
    'your-cms-element-name__config-element' + '__' + elementId,
    yourCallback,
{ selectors: yourSelectors });
```
