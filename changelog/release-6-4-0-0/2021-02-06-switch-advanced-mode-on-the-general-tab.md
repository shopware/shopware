---
title: Switch advanced mode on the general tab
issue: NEXT-13305
---
# Administration
* Added new `sw-product-settings-mode` component in `sw-product` module.
* Added `showSettingsInformation` prop in `/module/sw-product/component/sw-product-basic-form/index.js`.
* Added computed properties in `/module/sw-product/view/sw-product-detail-base/index.js`.
    * `currentUser`
    * `userModeSettingsRepository`
    * `userModeSettingsCriteria`
    * `getModeSettingGeneralTab`
    * `getModeSettingSpecificationsTab`
* Added methods in `/module/sw-product/page/sw-product-detail/index.js`.
    * `initAdvancedModeSettings`
    * `createUserModeSetting`
    * `getAdvancedModeDefaultSetting`
    * `getAdvancedModeSetting`
    * `saveAdvancedMode`
    * `onChangeAdvancedMode`
    * `changeDisplaySettings`
    * `getModeEnabledByKey`
    * `onChangeSettings`
* Added `sw-product-settings-mode` component in `/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig`.
