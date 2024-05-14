---
title: Change sw-password-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-password-field` to emit `update:value` instead of `input`
___
# Next Major Version Changes
## sw-password-field default event:
* Change event listeners from `@input="onInput"` to `@update:value="onInput"`
