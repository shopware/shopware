---
title: Vue3 compatibility for privileges.service
issue: NEXT-18182
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Administration
* Removed occurrences from `Vue.set` and `Vue.observable` from `src/app/service/privileges.service.js`
* Changed `src/app/service/privileges.service.js` to `src/app/service/privileges.service.ts` and transpiled it to TypeScript
