---
title: Apply validation for API system configs
issue: NEXT-6598
---
# Core
* Added class validation `src/Core/System/SystemConfig/Validation/SystemConfigValidation.php`
* Changed method `__construct` in file `src/Core/System/SystemConfig/Api/SystemConfigController.php` with syntax php 8.x constructor property promotion
* Changed method `batchSaveConfiguration` at file `src/Core/System/SystemConfig/Api/SystemConfigController.php` to validation form request before upsert into DB
* Added Dependency Injection `src/Core/System/DependencyInjection/configuration.xml`
___
# Administration
* Added prop error in files:
  * src/Administration/Resources/app/administration/src/app/component/form/sw-form-field-renderer/index.js
  * src/Administration/Resources/app/administration/src/app/component/form/sw-form-field-renderer/sw-form-field-renderer.html.twig
* Added function `mapSystemConfigErrors` into file `src/Administration/Resources/app/administration/src/app/service/map-errors.service.js`
* Added function getter `getSystemConfigApiError` and `countSystemError` into file `src/Administration/Resources/app/administration/src/app/state/error.store.js`
* Added class support resolved errors `src/Administration/Resources/app/administration/src/core/data/error-resolver.system-config.data.ts`
* Added handling exception at function `batchSave` in file `src/Administration/Resources/app/administration/src/core/service/api/system-config.api.service.js`
* Added function support display error message into view in file `src/Administration/Resources/app/administration/src/module/sw-settings/component/sw-system-config/index.js`
* Changed form body struct at function `saveAll` in file `src/Administration/Resources/app/administration/src/module/sw-settings/component/sw-system-config/index.js`
* Added attribute `error` in file `src/Administration/Resources/app/administration/src/module/sw-settings/component/sw-system-config/sw-system-config.html.twig` for display error message
