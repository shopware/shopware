---
title: Fix cms-form submit with basic captcha
issue: 8499
---
# Storefront
* Changed `FormCmsHandler` to only add on submit event handler when no basic captcha is present, as the basic captcha triggers the submit as well, thus preventing cases where the form is submitted twice and the captcha is considered invalid on second request.
