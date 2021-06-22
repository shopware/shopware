---
title: Integrate google recaptcha client side
issue: NEXT-14110
---
# Storefront
* Changed cookie consent's entry from `groupRequiredGoogleReCaptcha` to `groupRequiredCaptcha` in `\Shopware\Storefront\Framework\Cookie\CookieProvider` 
* Changed `\Shopware\Core\Framework\Api\Controller\CaptchaController` to filter `groupRequiredReCaptcha` when Google ReCaptcha V2 and V3 is not active
* Added new captcha class in `\Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2` to handle Google reCaptcha V2
* Added new storefront GoogleReCaptchaBase plugin in `src/Storefront/Resources/app/storefront/src/plugin/captcha/google-re-captcha/google-re-captcha-base.plugin.js`
* Added new storefront GoogleReCaptchaV2 plugin in `src/Storefront/Resources/app/storefront/src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin.js`
* Added new storefront GoogleReCaptchaV3 plugin in `src/Storefront/Resources/app/storefront/src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin.js`
* Changed method `_fireRequest` to publish a `beforeSubmit` event in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-ajax-submit.plugin.js`
* Changed method `_onFormSubmit` to publish a `beforeSubmit` event in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-validation.plugin.js`
* Added a new method `sendAjaxFormSubmit` in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-ajax-submit.plugin.js` to continuously send the request after validation
* Added a new method `sendAjaxFormSubmit` in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` to continuously send the request after validation
* Added a new method `sendAjaxFormSubmit` in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-cms-handler.plugin.js` to continuously send the request after validation
* Deprecated `beforeFireRequest` event in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-ajax-submit.plugin.js`, using `beforeSubmit` instead
* Deprecated `onFormSubmit` event in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-validation.plugin.js`, using `beforeSubmit` event instead
* Deprecated `onFormSubmit` event in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-submit-loader.plugin.js`, using `beforeSubmit` event instead
* Added a new scss asset in `src/Storefront/Resources/app/storefront/src/scss/layout/_recaptcha.scss` to hide recaptcha badge
* Changed the position of the block `component_account_register_captcha` in `src/Storefront/Resources/views/storefront/component/account/register.html.twig` to show the captcha below privacy notice
* Added a new captcha template for Google ReCaptcha v2 in `src/Storefront/Resources/views/storefront/component/captcha/googleReCaptchaV2.html.twig`
* Changed the filename of `src/Storefront/Resources/views/storefront/component/captcha/google-re-captcha-v3.html.twig` to `src/Storefront/Resources/views/storefront/component/captcha/googleReCaptchaV3.html.twig`
* Added a new twig component in `src/Storefront/Resources/views/storefront/component/recaptcha.html.twig` to include recaptcha library from google when recaptcha is enabled
* Added a new check if the invalid element has the `data-skip-report-validity` attribute in `_onScrollEnd` method in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-scroll-to-invalid-field.plugin.js` to only focus on focusable element
