---
title: Fix hydration of wrong json attributes
issue: NEXT-12225
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added method `isJsonObjectField` to `entity-definition`
* Added method `isJsonListField` to `entity-definition`
* Added hydration for empty json fields in `entity-hydrator` to fix issues with empty json objects which get hydrated as arrays. This works in PHP but in JS array can not have properties therefore it will be converted to an empty object. 
