---
title: Upgrade information for captcha solution
issue: NEXT-8225
---
# Storefront
* Changed `component_captcha_base` block to define random formId and set captchas in `views/storefront/component/captcha/base.html.twig`

# Upgrade Information

## New Captcha Solution
* We deprecated the system config `core.basicInformation.activeCaptchas` with only honeypot captcha and upgraded to system config `core.basicInformation.activeCaptchasV2` with honeypot, basic captcha, Google reCaptcha v2, Google reCaptcha v3
### Setting captcha in administration basic information
* Honeypot captcha is activated by default
* Select to active more basic captcha, Google reCaptcha
* With Google reCaptcha v2 checkbox:
  Configure the correct site key and secret key for reCaptcha v2 checkbox
  Turn off option `Invisible Google reCAPTCHA v2`
* With Google reCaptcha v2 invisible:
  Configure the correct site key and secret key for reCaptcha v2 invisible
  Turn on option `Invisible Google reCAPTCHA v2`
* With Google reCaptcha v3:
  Configure the correct site key and secret key for reCaptcha v3
  Configure `Google reCAPTCHA v3 threshold score`, default by 0.5
### How to adapt the captcha solution upgrade?
* Add `Shopware\Storefront\Framework\Captcha\Annotation\Captcha` annotation to StorefrontController-Routes to apply captcha protection.
* Due to captcha forms will be displayed when activated, be aware that the captcha input might break your layout
#### Before
```php
{% sw_include '@Storefront/storefront/component/captcha/base.html.twig' with { captchas: config('core.basicInformation.activeCaptchas') } %}
```
#### After
```php
{% sw_include '@Storefront/storefront/component/captcha/base.html.twig'
    with {
        additionalClass : string,
        formId: string,
        preCheck: boolean
    }
%}
```

We have a default captchas config, so now you don't need to provide a captchas parameter to the component, if you provide the captchas parameter, they will be overridden

Options:
- `additionalClass`: (optional) default is `col-md-6`,
- `formId`: (optional) - you can add the custom `formId`,
- `preCheck`: (optional) default is `false` - if true it will call an ajax-route to pre-validate the captcha, before the form is submitted. When using a native form, instead of an ajax-form, the `precheck` should be `true`.
