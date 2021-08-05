---
title: Igmplement a new cookie entry for Google reCAPTACHAv3 in Cookie Manager
issue: NEXT-14137
---
# Storefront
* Added a cookie entry for technical required cookie group named `groupRequiredGoogleReCaptcha` in `\Shopware\Storefront\Framework\Cookie\CookieProvider` when `core.basicInformation.activeCaptchas` setting includes `google-re-captcha-v3`; 
* Added new Storefront Captcha implementation in `\Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3`.
* Added new storefront captcha implementation template `src/Storefront/Resources/views/storefront/component/captcha/google-re-captcha-v3.html.twig`.
