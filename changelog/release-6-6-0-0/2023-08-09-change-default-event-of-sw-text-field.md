---
title: Change default event of sw-text-field
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-text-field` to emit `update:value` instead of `input`
___
# Next Major Version Changes
## sw-text-field default event:
* Change event listeners from `@input="onInput"` to `@update:value="onInput"`
