---
title: Fixed double form submit with reCaptcha plugin
issue: NEXT-40865
---
# Storefront
* Changed the `google-re-captcha-base.plugin.js` to only subscribe to the proper form submit event and call an `event.preventDefault()` to prevent double form submits, which lead to empty carts for guest orders.
