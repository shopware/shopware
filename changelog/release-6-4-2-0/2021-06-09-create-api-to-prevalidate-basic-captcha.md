---
title: Create api to prevalidate basic captcha
issue: NEXT-15346
---
# Storefront
* Changed function `onCaptchaFailure` in `Shopware\Storefront\Controller\ErrorController`
* Added new ajax route to pre-validate basic captcha in `Shopware\Storefront\Controller\CaptchaController`
* Added block `component_basic_captcha_fields_title_input` in `src/Storefront/Resources/views/storefront/component/captcha/basicCaptchaFields.html.twig`
* Changed `BasicCaptchaPlugin` in `Resources/app/storefront/src/plugin/captcha/basic-captcha.plugin.js`
