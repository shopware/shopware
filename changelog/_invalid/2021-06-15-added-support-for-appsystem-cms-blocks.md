---
title: Added support for custom CMS blocks from apps
issue: NEXT-14409
flag: FEATURE_NEXT_14408

---
# Administration
* Added method `buildAndCreateComponent` to `src/app/adapter/view/vue.adapter.js`
* Added new service `AppCmsService` which is responsible to create and register custom CMS blocks from apps
* Added new service injection in `src/module/sw-cms/page/sw-cms-detail/index.js` which fetches and registers custom CMS blocks when the detail page of the CMS getting opened by the user.