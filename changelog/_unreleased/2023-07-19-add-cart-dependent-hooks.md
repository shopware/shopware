---
title: Add cart dependent hooks
issue: NEXT-26807
---
# Core
* Added new property `source` to the cart, that adds a suffix to app script hooks, that are relevant for the checkout process. This can be used to add hooks that are only relevant for separate checkout processes like subscriptions, multi-cart, etc.
* Added new `CartFactory` to manipulate the cart source when being created
* Added new `CartLoadedEvent` to implementations of `CartPersister`
