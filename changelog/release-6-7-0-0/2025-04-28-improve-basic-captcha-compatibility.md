---
title: Improve basic captcha form validation compatibility
issue: #8499
---
# Storefront
* Changed `storefront/src/plugin/captcha/basic-captcha.plugin.js` to use an additional fake input which stays invalid until the captcha is validated. This improves compatibility with the native `checkValidity()` method of the form, so the method will return false until the captcha is valid. This will also improve compatibility with other form plugins that make use of the native form validation.