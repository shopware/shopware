---
title: Fix to handle Google ReCaptcha double form submit
issue: NEXT-00000
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Storefront
* Added check for `data-google-re-captcha` attribute presence in the form to `form-auto-submit.plugin.js` and `form-ajax-submit.plugin.js` to avoid calling `sendAjaxFormSubmit`, the `basic-captcha.plugin.js` plugin will call it, if captcha test succeeds.
