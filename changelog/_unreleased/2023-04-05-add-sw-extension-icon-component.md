---
title: Add sw-extension-icon component
issue: NEXT-25582
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Administration
* Added component `src/module/sw-extension/component/sw-extension-icon`
* Changed `src/app/component/app/sw-app-action-button` to use `src/module/sw-extension/component/sw-extension-icon`
* Changed `src/app/service/extension-helper.service` to TypeScript
* Changed `src/module/sw-extension/component/sw-extension-card-base/sw-extension-card-base.html.twig` to use `src/module/sw-extension/component/sw-extension-icon`
* Changed `src/module/sw-extension/page/sw-extension-config/sw-extension-config` to use `src/module/sw-extension/component/sw-extension-icon`
* Added new type `ExntesionSource` in `src/module/sw-extension/service/extension-store-action.service.ts`
* Changed `src/module/sw-first-run-wizard/component/sw-plugin-card` to Typescript
