---
title: Improve focus handling for Off-Canvas cart and quantity selector
issue: NEXT-26712
---
# Storefront
* Added new methods `saveFocusStatePersistent` and `resumeFocusStatePersistent` to `window.focusHandler` to allow resuming a saved focus after page reload using `window.sessionStorage`
* Added new plugin options `autoFocus` and `focusHandlerKey` to `Resources/app/storefront/src/plugin/offcanvas-cart/offcanvas-cart.plugin.js`
* Added new plugin options `autoFocus` and `focusHandlerKey` to `Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js`
* Changed `QuantitySelectorPlugin` to also dispatch `change` events on the `[+]` or `[-]` buttons, so it can be determined if the event target was the button or the input field.
* Changed `QuantitySelectorPlugin` to use `init()` instead of internal `_init()` of the plugin base class, so the `this._initialized` property is set correctly.
* Changed `OffCanvasCartPlugin` to include `tabindex="-1"` on the `.offcanvas` HTML wrapper like documented by Bootstrap. This makes the Off-Canvas cart immediately close-able via ESC.
___
# Upgrade Information

## Persistent mode for `focusHandler`
The `window.focusHandler` now supports a persistent mode that can be used in case the current focus is lost after a page reload.
When using methods `saveFocusStatePersistent` and `resumeFocusStatePersistent` the focus element will be saved inside the `sessionStorage` instead of the window object / memory.

The persistent mode requires a key name for the `sessionStorage` as well as a unique selector as string. It is not possible to save element references into the `sessionStorage`.
The unique selector will be used to find the DOM element during `resumeFocusStatePersistent` and re-focus it.
```js
// Save the current focus state
window.focusHandler.saveFocusStatePersistent('special-form', '#unique-id-on-this-page');

// Something happens and the page reloads
window.location.reload();

// Resume the focus state for the key `special-form`. The unique selector will be retrieved from the `sessionStorage` 
window.focusHandler.resumeFocusStatePersistent('special-form');
```

By default, the storage keys are prefixed with `sw-last-focus`. The above example will save the following to the `sessionStorage`:

| key                          | value                     |
|------------------------------|---------------------------|
| `sw-last-focus-special-form` | `#unique-id-on-this-page` |

## Automatic focus for `FormAutoSubmitPlugin`
The `FormAutoSubmitPlugin` can now try to re-focus elements after AJAX submits or full page reloads using the `window.focusHandler`.
This works automatically for all form input elements inside an auto submit form that have a `[data-focus-id]` attribute that is unique.

The automatic focus is activated by default and be modified by the new JS-plugin options:

```js
export default class FormAutoSubmitPlugin extends Plugin {
    static options = {
        autoFocus: true,
        focusHandlerKey: 'form-auto-submit'
    }
}
```

```diff
<form action="/example/action" data-form-auto-submit="true">
    <!-- FormAutoSubmitPlugin will try to restore previous focus on all elements with da focus-id -->
    <input 
        class="form-control"
+        data-focus-id="unique-id"
    >
</form>
```
