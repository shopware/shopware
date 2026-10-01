---
title: Use native iteration instead of iterator helper
issue: NEXT-39407
---
# Storefront
* Deprecated `iterator.helper.js`. Use native iteration like `forEach` directly instead.
* Changed and fixed `FormSerializeUtil.serializeJson()` so it does not always return an empty object. 
___
# Upgrade Information
## Deprecate Storefront `Iterator` helper

The `iterator.helper.js` is deprecated and should be replaced with native alternatives.

**Search for `.iterate(` in your codebase and replace it with native iterators.**

You are not limited to the examples below, and you can use other looping methods depending on you use case.

### Iterate NodeList

A `NodeList` (yielded by `querySelectorAll`) is already iterable. You can directly use `forEach` or `for...of`.

```diff
const exampleNodeList = document.querySelectorAll('.btn');

- Iterator.iterate(exampleNodeList, item => {});
+ exampleNodeList.forEach(item => {});
```

### Iterate Array

An `Array` is already iterable. You can directly use `forEach`.

```diff
const exampleArray = ['item1', 'item2'];

- Iterator.iterate(exampleArray, (item) => {});
+ exampleArray.forEach((item) => {});
```

### Iterate Map

A `Map` is already iterable. You can directly use `forEach` or `for...of`

```diff
const exampleMap = new Map();
exampleMap.set('a', 1);
exampleMap.set('b', 2);

- Iterator.iterate(exampleMap, (value, key) => {});
+ exampleMap.forEach((value, key) => {});
```

### Iterate HTMLCollection

A `HTMLCollection` (yielded by `getElementsByClassName`) is an array-like object that does not support `forEach` directly.
You can transform it into an array first or use a `for...of` loop.

```diff
const exampleCollection = document.getElementsByClassName('btn');

- Iterator.iterate(exampleCollection, (element) => {}
+ Array.from(exampleCollection).forEach((element) => {}
```

It is recommended to use `Array.from` to make a copy since the `HTMLCollection` is live and is automatically updated when the underlying document is changed.

### Iterate Object

For `Object` you can use `Object.keys()`, `Object.entries()` etc. depending on your use case. You can then use `for...of`, `forEach` or other suitable loops.

```diff
const exampleObj = { name: 'Example name', year: '2005' };

- Iterator.iterate(exampleObj, (value, key) => {});
+ for (const [key, value] of Object.entries(exampleObj)) {}
```

### Iterate FormData

For `FormData` objects you can use `FormData.keys()`, `FormData.entries()` etc. depending on your use case. You can then use `for...of`, `forEach` or other suitable loops.

```diff
const formData = new FormData();
formData.append('username', 'Groucho');
formData.append('accountNum', 123456);

- Iterator.iterate(formData, (value, key) => {});
+ for (const [key, value] of formData.entries()) {}
```