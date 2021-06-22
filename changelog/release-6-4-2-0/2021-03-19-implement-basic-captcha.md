---
title: Implement basic captcha
issue: NEXT-14111
---
# Storefront
* Added `Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPagelet`
* Added new page loader `Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPageletLoader` to load `Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPagelet`
* Added new event `Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPageletLoadedEvent` to be fired after `Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPagelet` is load
* Added new class `CaptchaController` in `Shopware\Storefront\Controller`
* Added new class `BasicCaptchaImage` in `Shopware\Storefront\Framework\Captcha\BasicCaptcha`
* Added new class `BasicCaptchaGenerator` in `Shopware\Storefront\Framework\Captcha\BasicCaptcha`
* Added new class `BasicCaptcha` in `Shopware\Storefront\Framework\Captcha`
* Added new method `shouldBreak` in `Shopware\Storefront\Framework\Captcha\AbstractCaptcha`
* Added new method `onCaptchaFailure` in `Shopware\Storefront\Controller\ErrorController`
* Added new BasicCaptchaPlugin `Resources/app/storefront/src/plugin/captcha/basic-captcha.plugin.js` to handle logic for basic captcha element
* Added new twig file `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig` to implement basic captcha
* Added new twig file `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptchaImage.html.twig` to show basic captcha image
* Added new block `component_basic_captcha` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig`
* Added new block `component_basic_captcha_image` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig`
* Added new block `basic_captcha_content_image` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptchaImage.html.twig`
* Added new block `component_basic_captcha_refresh_icon` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig`
* Added new block `component_basic_captcha_fields_title_label` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig`
* Added new block `component_basic_captcha_fields_title_input` in `src/Storefront/Resources/views/storefront/compenent/captcha/basicCaptcha.html.twig`
* Changed function `validateCaptcha` in `Shopware\Storefront\Framework\Captcha\CaptchaRouteListener`
* Changed function `supports` in `Shopware\Storefront\Framework\Captcha\HoneypotCaptcha`
