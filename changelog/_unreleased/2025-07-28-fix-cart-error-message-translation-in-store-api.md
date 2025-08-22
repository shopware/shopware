---
title: Fix cart error message translation in store api
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `Shopware\Core\Checkout\Cart\CartRuleLoader` to override the cart error messages with the correct translated string
* Added `$translatedMessage` field + get & set functions to `Shopware\Core\Checkout\Cart\Error\Error`
