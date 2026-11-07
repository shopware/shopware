---
title: Show a reCAPTCHA message when the reCAPTCHA token is missing
issue: 21511
---

# Storefront

* Added the `grecaptcha` validator to `src/Storefront/Resources/app/storefront/src/helper/form-validation.helper.js`.
  It fails when the reCAPTCHA field `_grecaptcha_v3` or `_grecaptcha_v2` has no token and shows the new snippet `error.VIOLATION::RECAPTCHA_TOKEN_REQUIRED`.
* Changed `@Storefront/storefront/component/captcha/googleReCaptchaV2.html.twig` and `@Storefront/storefront/component/captcha/googleReCaptchaV3.html.twig` to use `data-validation="grecaptcha"` instead of `data-validation="required"` with the `ACCESSIBILITY_TWEAKS` feature flag.
  Customers now see a reCAPTCHA-specific message instead of "Input should not be empty." when reCAPTCHA is still loading or blocked, as in 6.7.
* Added the `grecaptchaToken` message to `window.validationMessages` in `@Storefront/storefront/utilities/form-validation-config.html.twig`, as in 6.7.
  Without it, the `grecaptcha` validator falls back to the `required` message.
