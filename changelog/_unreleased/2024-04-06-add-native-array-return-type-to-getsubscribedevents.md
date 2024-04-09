---
issue: NEXT-34921
title: Add native array return type to getSubscribedEvents()
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added native `array` return type to `Shopware\Core\System\UsageData\Subscriber\ShopIdChangedSubscriber::getSubscribedEvents()` and `Shopware\Core\System\UsageData\Consent\ConsentReporter::getSubscribedEvents()`
___
# Storefront
* Added native `array` return type to `Shopware\Storefront\Framework\Twig\TwigDateRequestListener::getSubscribedEvents()`
