---
title: Cart context fetch change
author: Mark Ringtved Nielsen
author_email: mrn@geni.digital
author_github: mrn-rigtved & ringtved
---
# Storefront
* Changed `cart-widget.plugin.init()` removed call `cart-widget.plugin.fetch()` so we dont fetch eveytime we init the storefront plugin.
* Changed `cart-widget.plugin.fetch()` added `if(storedContent)` to check if we have data in storage else fetch the content.
* Changed `session storage` comments to `local storage` we use the localstorage there for the comments need to match.
