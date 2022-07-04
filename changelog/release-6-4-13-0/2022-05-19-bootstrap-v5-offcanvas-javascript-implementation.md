---
title: Bootstrap v5 OffCanvas JavaScript implementation
issue: NEXT-21024
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Changed OffCanvas base class `Resources/app/storefront/src/plugin/offcanvas/offcanvas.plugin.js` to support Bootstrap v5 OffCanvas
    * Added new property `bsOffcanvas` to store Bootstraps OffCanvas plugin instance
    * Deprecated all implementations which set or remove the "opened CSS class" manually
    * Added usages of Bootstrap v5 OffCanvas plugin `show` and `hide` methods to control the OffCanvas
___
# Upgrade Information
## Add support for Bootstrap v5 OffCanvas

Bootstrap has released a new OffCanvas component in version 5. To stick more towards the Bootstrap framework in the Storefront,
we have decided to migrate our custom OffCanvas solution to the Bootstrap v5 OffCanvas component.

Find out more about the Bootstrap OffCanvas here: https://getbootstrap.com/docs/5.1/components/offcanvas/

In general, the changes are mostly done internally, so that interacting with the OffCanvas via JavaScript can remain the same.
However, when the major flag `V6_5_0_0` is activated, the OffCanvas module will open a Bootstrap OffCanvas with slightly different elements/classes.

Let's take a look at an example, which opens an OffCanvas using our OffCanvas module `src/plugin/offcanvas/offcanvas.plugin`:
```js
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';

// No need for changes in general usage!
OffCanvas.open(
    'My content', // Content to render inside the OffCanvas
    () => {},     // Callback function to run after opening the OffCanvas
    'right',      // Position
    true,         // Can be closed via the backdrop
    100,          // Delay
    true,         // Full-width OffCanvas
    'my-class'    // Additional CSS classes for the OffCanvas element
);
```
The above example, will work as expected, but it will yield different HTML in the DOM:

**Opened OffCanvas with current implementation**
```html
<div class="offcanvas is-right is-open">
    My content
</div>
<div class="modal-backdrop modal-backdrop-open"></div>
```

**Opened OffCanvas with Bootstrap v5 (V6_5_0_0=true)**
```html
<!-- `right` is now called `end` in Bootstrap v5. This will be converted automatically. -->
<!-- `show` is now used instead of `is-open` to indicate the active state. -->
<div class="offcanvas offcanvas-end show" style="visibility: visible;" aria-modal="true" role="dialog">
    My content
</div>

<!-- Bootstrap v5 uses a dedicated backdrop for the OffCanvas. -->
<div class="offcanvas-backdrop fade show"></div>
```

Furthermore, Bootstrap v5 needs slightly different HTML inside the OffCanvas itself. This needs to be considered,
if you inject your HTML manually via JavaScript:

```js
import OffCanvas from 'src/plugin/offcanvas/offcanvas.plugin';
import Feature from 'src/helper/feature.helper';

let offCanvasContent;

// OffCanvas now needs additional `offcanvas-header`
// Content class `offcanvas-content-container` is now `offcanvas-body`
if (Feature.isActive('v6.5.0.0')) {
    offCanvasContent = `
    <div class="offcanvas-header p-0">
        <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
            Close
        </button>
    </div>
    <div class="offcanvas-body">
        Content
    </div>
    `;
} else {
    offCanvasContent = `
    <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
        Close
    </button>
    <div class="offcanvas-content-container">
        Content
    </div>
    `;
}

// No need for changes in general usage!
OffCanvas.open(
    offCanvasContent // Use altered HTML, if Bootstrap v5 is used
);
```

If you use `src/plugin/offcanvas/ajax-offcanvas.plugin` with a response which is based on `Resources/views/storefront/utilities/offcanvas.html.twig`, 
you don't need to change anything. The markup inside the OffCanvas twig file is adjusted automatically to Bootstrap v5 markup.

___
# Next Major Version Changes
## Storefront OffCanvas requires different HTML:

The OffCanvas module of the Storefront (`src/plugin/offcanvas/ajax-offcanvas.plugin`) was changed to use the Bootstrap v5 OffCanvas component in the background.
If you pass a string of HTML manually to method `OffCanvas.open()`, you need to adjust your markup according to Bootstrap v5 in order to display the close button and content/body.

See: https://getbootstrap.com/docs/5.1/components/offcanvas/

### Before
```js
const offCanvasContent = `
<button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
    Close
</button>
<div class="offcanvas-content-container">
    Content
</div>
`;

OffCanvas.open(offCanvasContent);
```

### After
```js
// OffCanvas now needs additional `offcanvas-header`
// Content class `offcanvas-content-container` is now `offcanvas-body`
const offCanvasContent = `
<div class="offcanvas-header p-0">
    <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
        Close
    </button>
</div>
<div class="offcanvas-body">
    Content
</div>
`;

// No need for changes in general usage!
OffCanvas.open(offCanvasContent);
```
