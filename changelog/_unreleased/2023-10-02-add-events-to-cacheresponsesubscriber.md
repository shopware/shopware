---
title: Add events to CacheResponseSubscriber
issue: /
author: Rune Laenen
author_email: rune@laenen.me
author_github: runelaenen
---
# Storefront
* Added `CacheResponseGenerateHashEvent` class
* Added `CacheResponseSystemStatesEvent` class
* Dispatches `CacheResponseGenerateHashEvent` in `buildCacheHash`
* Dispatches `CacheResponseSystemStatesEvent` in `getSystemStates`
