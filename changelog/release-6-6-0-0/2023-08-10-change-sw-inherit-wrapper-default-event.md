---
title: Change sw-inherit-wrapper default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-inherit-wrapper` to emit `update:value` instead of `input`
___
# Next Major Version Changes
## sw-inherit-wrapper default event:
* Change event listeners from `@input="onInput"` to `@update:value="onInput"`
