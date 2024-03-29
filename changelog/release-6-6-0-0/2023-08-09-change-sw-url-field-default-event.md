---
title: Change sw-url-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-url-field` default event from `input` to `update:value`
___
# Next Major Version Changes
## Breaking Change 1:
* Change `sw-url-field` input listeners `@input="onIput"` to `@update:value="onInput"`
