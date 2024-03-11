---
title: Fix Google ReCaptcha V3 cannot refresh the token on submit
issue: NEXT-32279
author: Cuong Huynh
author_github: @cuonghuynh
---
# Storefront
* Changed property assignment from `this.formSubmitting` to `this._formSubmitting` in `Resources/app/storefront/src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin.js` to fix token refresh on submit
