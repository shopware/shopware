---
title: Fix unsubscribe name
issue: NEXT-31423
author: Tommy Quissens
author_email: tommy.quissens@meteor.be
author_github: @quisse
---
# Storefront
* Changed method `unsubscribe` in `NativeEventEmitter` class to fix a bug with `sort()` function that sorts its array, which prevent unsubscribing of events to an element since the name at key `0` is sorted.
