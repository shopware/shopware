---
title: Fix scroll up button accessibility
issue: NEXT-34090
---
# Storefront
* Changed `ScrollUpPlugin` to focus the first focus-able element after scroll so the button actually works with screen readers
* Changed `Resources/views/storefront/layout/scroll-up.html.twig` and removed `aria-hidden` from the button
* Added new methods to `DomAccessHelper`: `getFocusableElements`, `getFirstFocusableElement`  and `getLastFocusableElement`
___
# Upgrade Information

## New `DomAccessHelper` methods to find focusable elements

The `DomAccessHelper` now supports new methods to find DOM elements that can have keyboard focus.
Optionally, an element can be provided as a parameter to only search within this given element. By default, the document body will be used.

```js
import DomAccess from 'src/helper/dom-access.helper';

// Find all focusable elements
DomAccess.getFocusableElements();

// Return the first focusable element
DomAccess.getFirstFocusableElement();

// Return the last focusable element
DomAccess.getLastFocusableElement();

// Only search for focus-able elements inside the given DOM node
const element = document.querySelector('.special-modal-container');
DomAccess.getFirstFocusableElement(element);
```
