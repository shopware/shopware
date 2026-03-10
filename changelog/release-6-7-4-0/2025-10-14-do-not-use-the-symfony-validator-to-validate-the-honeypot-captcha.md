---
title: Do not use the Symfony validator to validate the honeypot captcha
author: Max
author_email: max@swk-web-com
author_github: @aragon999
---

# Storefront
* Changed the `Shopware\Storefront\Framework\Captcha\HoneypotCaptcha` to not use the Symfony validator to validate the captcha, if the behavior of the captcha should be changed, overwrite the `isValid` method directly
___
# Next Major Version Changes
## Symfony validator is not used to validate the honeypot captcha
The Symfony validator is not used to check the validity of the honeypot captcha, so if it was used to change the validity of the honeypot captcha, overwrite the `isValid` method of the honeypot captcha directly.
