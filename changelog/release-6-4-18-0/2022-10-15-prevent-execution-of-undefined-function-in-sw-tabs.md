---
title: Prevent execution of undefined function in sw-tabs
issue: NEXT-23779
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Changed administration component `sw-tabs` to prevent execution of `this.$scopedSlots.default()` if there is no default slot
