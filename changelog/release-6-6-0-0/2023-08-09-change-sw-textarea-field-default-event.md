---
title: Change sw-textarea-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-textarea-field` default event from `input` to `update:value`
___
# Next Major Version Changes
## sw-textarea-field default event:
* Change event listeners from `@input="onInput"` to `@update:value="onInput"`
