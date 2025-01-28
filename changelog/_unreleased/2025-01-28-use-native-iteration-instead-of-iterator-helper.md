---
title: Use native iteration instead of iterator helper
issue: #0000
---
# Storefront
* Deprecated `iterator.helper.js`. Use native iteration like `forEach` directly instead.
___
# Upgrade Information
## Deprecate Storefront `Iterator` helper

The `iterator.helper.js` is deprecated and should be replaced with native alternatives.

### Iterate NodeList

A `NodeList` (yielded by e.g. `querySelectorAll`) is already iterable, and you can directly use `forEach`.

```diff
- Iterator.iterate(exampleNodeList, item => {});
+ exampleNodeList.forEach(item => {})
```

### Iterate Array

An `Array` is already iterable, and you can directly use `forEach`.

```diff
const exampleArray = ['item1', 'item2'];

- Iterator.iterate(exampleArray, item => {});
+ exampleArray.forEach(item => {})
```

### Iterate Map

A `Map` is already iterable, and you can directly use `forEach` or `for-of`

```diff
const exampleMap = new Map();
map1.set('a', 1);
map1.set('b', 2);

- Iterator.iterate(exampleMap, item => {});
+ exampleMap.forEach((value, key) => {})
```

### Iterate HTMLCollection

### Iterate Object