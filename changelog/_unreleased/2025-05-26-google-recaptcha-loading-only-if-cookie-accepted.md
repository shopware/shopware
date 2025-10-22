---
title: Google reCAPTCHA loading only if cookie accepted
issue: 9451
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `main.js` to add script for calling reCAPTCHA functions also if script is loaded later.
* Changed `google-re-captcha-base.plugin.js` to load the reCAPTCHA script only if the technical required cookies are accepted.
* Changed `recaptcha.html.twig` to not directly download sources, but to load them via the `google-re-captcha-base.plugin.js` script.
