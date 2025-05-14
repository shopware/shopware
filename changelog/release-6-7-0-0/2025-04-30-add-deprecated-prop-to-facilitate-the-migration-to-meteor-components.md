---
title: Add deprecated prop to facilitate the migration to meteor components
issue: 8859
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Added `deprecated` prop to all components that are being replaced by Meteor Components.
___
# Upgrade Information

## Remove of sw- wrapper components
All the sw- wrapper components will be removed in the next major version. The Meteor Components will be used directly instead of the sw- wrapper components.

## Use `deprecated` prop
This prop is used to use deprecated components to keep the same API and not breaking previous code. This way the same code is compatible with Shopware 6.6 and Shopware 6.7 as this new `deprecated` prop will be ignored in 6.6. This prop will be removed in the next major version together with the sw- wrapper components.

```html
<!-- Uses mt-button in 6.7 and sw-button-deprecated in 6.6 -->
<template>
    <sw-button />
</template>


<!-- Uses sw-button-deprecated in 6.6 and 6.7 -->
<template>
    <sw-button deprecated />
</template>
```
