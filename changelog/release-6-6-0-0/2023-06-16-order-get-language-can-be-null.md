---
title: Allow usage of language property on OrderEntity when not loading the language association
issue: NEXT-31655
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed return type of `\Shopware\Core\Checkout\Order::getLanguage()` to also allow null
* Changed parameter type of `\Shopware\Core\Checkout\Order::setLanguage()` to also allow null
* Changed PHPdoc type of `\Shopware\Core\Checkout\Order::$language` to also allow null
