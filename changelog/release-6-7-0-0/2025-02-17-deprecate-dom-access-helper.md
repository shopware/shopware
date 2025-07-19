---
title: Deprecate DomAccess Helper for 6.8
issue: NEXT-40618
---
# Storefront
* Deprecated DomAccess Helper `storefront/src/helper/dom-access.helper.js` for 6.8 and replaced all usages within core files. The focus related methods were moved to FocusHandler Helper `storefront/src/helper/focus-handler.helper.js`. Use them as a replacement for `getFocusableElements()`, `getFirstFocusableElement()`, and `getLastFocusableElement()`. 
* Changed default values in `form-country-state-select.plugin.js` of the options `vatIdRequired`, `stateRequired`, `zipcodeRequired`, `initialCountryAttribute`, and `initialCountryStateAttribute` to include the `data-` prefix for the right data attribute naming.
___
# Next Major Version Changes
## Storefront
### Deprecated DomAccess Helper
We deprecated DomAccess Helper, because it does not add much value compared to native browser APIs and to reduce Shopware specific code complexity. You simply replace its usage with the corresponding native methods. Here are some RegEx to help you:

#### hasAttribute()  
**RegEx**: `DomAccess\.hasAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.hasAttribute($2)`

#### getAttribute()
**RegEx**: `DomAccess\.getAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.getAttribute($2)`

#### getDataAttribute()
**RegEx**: `DomAccess\.getDataAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.getAttribute($2)`

#### querySelector()
**RegEx**: ``DomAccess\.querySelector\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``  
**Replacement**: `$1.querySelector($2)`

#### querySelectorAll()
**RegEx**: ``DomAccess\.querySelectorAll\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``  
**Replacement**: `$1.querySelectorAll($2)`

#### getFocusableElements()
This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const focusableElements = window.focusHandler.getFocusableElements();
```

#### getFirstFocusableElement()
This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const firstFocusableEl = window.focusHandler.getFirstFocusableElement();
```

#### getLastFocusableElement()
This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const lastFocusableEl = window.focusHandler.getLastFocusableElement();
```
