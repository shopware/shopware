---
title: Fix translator trans locale fallback if locale parameter not set
issue: NEXT-00000
author: Florian Kasper
author_email: fk@bitsandlikes.de
author_github: flkasper
---

# Core

* Sets the initial locale of `Shopware\Core\Framework\Adapter\Translation\Translator` in the constructor to shopware default language locale using `Shopware\Core\Framework\Adapter\Translation\Translator::getFallbackLocale`.
* Use current locale of `Shopware\Core\Framework\Adapter\Translation\Translator` as fallback if no locale is set as parameter when using `Shopware\Core\Framework\Adapter\Translation\Translator::trans`.
