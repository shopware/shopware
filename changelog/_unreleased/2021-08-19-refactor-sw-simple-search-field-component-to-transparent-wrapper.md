---
title: Refactor `sw-simple-search-field` component to transparent wrapper
issue: NEXT-16271
flag: FEATURE_NEXT_16271
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---
# Administration
* Deprecated custom `model` definition on `sw-simple-search-field` component from `model: { prop: 'searchTerm', event: 'search-term-change' }` to not debounced default handling `@input` and `value` property.
* Deprecated `searchTerm` property on `sw-simple-search-field` with version 6.5.0, use `value` instead.
* Deprecated `watch: { term() { … }` on `sw-card-filter` component.
* Added `onSearchTermChange()` method on `sw-card-filter` component to replace deprecated `term` watcher.
* Deprecated `watch: { searchTerm() { … }` on `sw-product-stream-grid-preview` component.
* Added `onSearchTermChange()` method on `sw-product-stream-grid-preview` component to replace depreacted `searchTerm` watcher.
* Deprecated `watch: { searchTerm() { … }` on `sw-media-field` component.
* Added `onSearchTermChange()` method on `sw-media-field` component to replace deprecated `searchTerm` watcher.
* Deprecated `watch: { term() { … }` on `sw-sidebar-media-item` component.
* Added `onSearchTermChange()` method on `sw-sidebar-media-item` component to replace deprecated `term` watcher.
* Deprecated `watch: { 'term'() { … }` on `sw-product-variants-configurator-prices` component.
* Added `onSearchTermChange()` method on `sw-product-variants-configurator-prices` component to replace deprecated `'term'()` watcher.
* Deprecated `watch: { searchTerm() { … }` on `sw-product-stream-modal-preview` component.
* Added `onSearchTermChange()` method on `sw-product-stream-modal-preview` component to replace deprecated `searchTerm` watcher.
* Deprecated `watch: { searchTerm() { … }` on `sw-sales-channel-product-assignment-categories` component.
* Added `onSearchTermChange()` method on `sw-sales-channel-product-assignment-categories` component to replace deprecated `searchTerm` watcher.
* Deprecated `watch: { term() { … }` on `sw-custom-field-list` component.
* Added `onSearchTermChange()` method on `sw-custom-field-list` component to replace deprecated `term` watcher.
* Added `doSearch` method on `sw-settings-product-feature-sets-values-card` component to keep concerns better seperated, between `@search-term-change` handler and multiple used search code.
___
# Upgrade Information

## removed custom `model` definition

The custom `model` definition is deprecated:
```js
model: {
  prop: 'searchTerm',
  event: 'search-term-change',
},
```
The component uses now standard vue model behavior. A `v-model` binding is now instantly and not debounced. If you `watch` on the `v-model` value you should switch your code to listen at `@search-term-change`.

## `v-model` usage

The `v-model` is not debounced anymore. If you use a `watch`er you must switch your `watch` code to a method and listen at the `@search-term-change` event.

Before:
```html
<sw-simple-search-field
  v-model="term"
  …
/>
```
```js
// component code
  data() {
    return {
      …
      term: null,
    };
  },
  …
  watch: {
    term() {
      this.onSeach(this.term);
    },
  },
  …
```

After:
```html
<sw-simple-search-field
  v-model="term"
  @search-term-change="onSearchTermChange"
/>
```
```js
// component code
  data() {
    return {
      term: null,
    };
  },
  …
  methods: {
    onSearchTermChange() {
      this.onSearch(this.term);
    },
  },
  …
```
## `searchTerm` property usage

Use `value` property instead.

Before:
```html
<sw-simple-search-field
  …
  :search-term="term"
  …
/>
```

After:
```html
<sw-simple-search-field
  …
  :value="term"
  …
/>
```

## `search-term-change` event usage

nothing changes here.
