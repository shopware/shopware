---
title: Hide & show content by device - Categories layout section preview
issue: NEXT-23445
---
# Administration
* Changed file `/src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-page-form/sw-cms-page-form.html.twig`
  * Added `sw-cms-page-form__device-actions` to block `sw_cms_page_form_section_name_wrapper` .
  * Added `sw-cms-page-form__block-device-actions` to header right of block `sw_cms_page_form_card`.
* Changed file `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-page-form/sw-cms-page-form.scss`
  * Changed max width of `sw-cms-page-form__section-action` on the viewport > 1360px.
  * Added css for `sw-cms-page-form__device-actions` and `sw-cms-page-form__block-device-actions`.
* Change file `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-page-form/index.js`
  * Added function `getSectionDeviceActive` return show/hide section on exactly viewport.
  * Added function `getBlockDeviceActive` return show/hide block on exactly viewport.
  * Added function `disabledFormField` to show alert when visibility are false.
