---
title: Removed global Vue2 event emitter from the `sw-order-detail` component
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Removed Vue2 `this.$root.$emit(...)` events from the `sw-order-detail` component, as they were doing nothing
* Deprecated `onChangeLanguage` of the `sw-order-detail` component
