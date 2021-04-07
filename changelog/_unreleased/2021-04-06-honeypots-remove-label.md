---
title: Honeypots can be ignored by bots since its labeled
issue: NEXT-14669
author: Juri Petersen
author_email: juri.petersen@visuellverstehen.de
author_github: @juripetersen
---
# Storefront
* Changed class from `div class="captcha--honeypot"` to `div class="register-shopware-surname"` to remove the appearance of the word honeypot inside `Resources/views/storefront/component/captcha/honeypot.html.twig` since it labels the honeypot and bots can ignore this field just by reading this string. Otherwise bots can still register users programmatically while ignoring the honeypot
