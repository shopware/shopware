---
title: Deprecate Iterator.helper in Storefront JS
date: 2025-02-06
area: framework
tags: [storefront, js, helper]
---

## Context

The Storefront JavaScript currently offers a helper class `src/helper/iterator.helper.js` that can iterate over different objects like Maps, FormData, plain arrays etc.
Using this approach has several downsides we want to address.

* It creates an unnecessary abstraction over the native alternative. Very often the `Iterator.iterate()` directly uses js plain `forEach` loop without any additional logic. Using the abstraction is actually creating more complexity.
* It prevents the developer from using the appropriate loop for the given data type and instead passes it to an iterator that does it with arbitrary data types.
* The iterator is a special shopware syntax that needs to be understood and documented. It is way easier to use web standards that everyone knows and that is also officially documented already.
* The usage throughout the codebase is inconsistent and the iterator helper is used alongside the native loops which can create confusion.
* It creates the impression that the data that is being iterated is some special custom object that needs a special iterator helper. In reality, it is a simple `NodeList` in 90% of the time.
* It creates an additional dependency/import in every file that is using it.

## Decision

For all the above reasons we have decided to deprecate `src/helper/iterator.helper.js`, stop using it, and use native alternatives instead.
We want to have a more lean and simple Storefront JavaScript that needs less special things to learn and understand.

## Consequences

* The `src/helper/iterator.helper.js` is deprecated for v6.8.0.
* The usages of `Iterator.iterate()` are replaced with native alternatives.
* See `2025-01-28-use-native-iteration-instead-of-iterator-helper.md` for the changelog and upgrade documentation.
