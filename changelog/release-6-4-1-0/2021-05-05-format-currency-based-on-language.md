---
title: Format currency based on language
issue: NEXT-11915
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Core
* Added a database call to get the `systemCurrencyISOCode`, to render the `index.html.twig` in the `AdministrationController`
* Added the `systemCurrencyISOCode` to the Vue setup `appContext` in `index.html.twig`
___
# Administration
* Added `systemCurrencyISOCode` to the `appContext` in the Vuex Store.
* Changed the `currency` filter, to now use the `systemCurrencyISOCode` as default, if a currency isn't specified
* Changed the `currency` filter, to now use the `currentLocale` as default, if a language isn't specified
