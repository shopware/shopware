---
title: Default handling for non specified salutations
date: 2023-06-28
area: core
tags: [adr, salutation]
---

## Context

If locale is not passed as argument directly to `Translator::trans()` method, fallback locale is always sent to `MessageFormatter::format()` method. Therefor, locale pluralization rules cannot be applied.

We want to use shopware locale instead to format translation messages correctly.

Lets say we have some translation string:

`"shop.cart.title" => "%count% продукт в кошику|%count% продукти в кошику|%count% продуктів в кошику"`

For example, for uk_UA locale, expected behavior is:
- `{{ "shop.cart.title"|trans({"%count%": 1}) }}` - 1 продукт в кошику
- `{{ "shop.cart.title"|trans({"%count%": 2 }}` - 2 продукти в кошику
- `{{ "shop.cart.title"|trans({"%count%": 5}) }}` - 5 продуктів в кошику
- `{{ "shop.cart.title"|trans({"%count%": 21}) }}` - 21 продукт в кошику

## Decision
- If locale is not passed as argument directly to `Translator::trans()` method, use MessageCatalogue::getLocale() method instead to retrieve shopware locale or use fallback one.

- `Symfony\Contracts\Translation\TranslatorTrait::getPluralizationRule()` method requires 2-digits or "underscore"-splited locale format to get correct position. Using `Symfony\Component\Intl\Locale::getFallback()` to convert locale format.
## Consequences
As a result of this decision, the following consequences will occur:

* Correct translation message formatting: using shopware locale to format translation messages correctly. Get correct position for locale pluralization rules.
