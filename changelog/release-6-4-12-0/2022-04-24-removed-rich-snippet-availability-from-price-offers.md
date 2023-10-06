---
title: Removed rich snippet availability from price offers
issue: NEXT-21337
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Removed possibly incorrect fixed `availability` from price offers if multiple prices are present, as the different prices are collected into an `AggregateOffer` which already has the correct `availability` defined
