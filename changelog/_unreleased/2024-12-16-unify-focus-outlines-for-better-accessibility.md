---
title: Unify focus outlines for better accessibility
issue: NEXT-39881
---
# Storefront
* Changed `$focus-ring-box-shadow` and `$form-select-focus-box-shadow` to have solid primary color for increased visibility.
* Changed the following classes to have the same outline appearance using `box-shadow`:
    * All `form-control`
    * All `form-select`
    * All `.btn-` variants
    * All `.btn-outline-` variants
* Deprecated custom styling for active filter class `filter-active`. Will be a Bootstrap button `.btn` instead of a `<span>` with hard-coded CSS.
* Deprecated custom styling for removal filter class `filter-reset-all`. Will use `--bs-btn-*` variables instead of hard-coded CSS.
* Deprecated custom styling for class `filter-active-remove`. The `filter-active` element will be the remove button.
* Deprecated `overflow: hidden` styling on `cms-block` class. Hidden overflow will be removed because it blocks visibility of focus states.
* Deprecated option `activeFilterLabelClass` from JS-plugin `ListingPlugin`. Use `activeFilterLabelClasses` to render the label classes and `activeFilterLabelSelector` as the selector for events.
* Deprecated option `activeFilterLabelRemoveClass` from JS-plugin `ListingPlugin`. Selector `activeFilterLabelClass` will be used to query the remove button.
* Added new option `activeFilterLabelClasses` to JS-plugin `ListingPlugin`.
* Added new option `activeFilterLabelSelector` to JS-plugin `ListingPlugin`.
* Removed class `btn-sm` from `filter-reset-all` button.
___
# Upgrade Information
## Storefront accessibility: Unify focus outline:
To improve the keyboard accessibility we will unify all focus outlines to have the same appearance.
Currently, the focus outlines are dependent on the color of the interactive element (e.g. light-green outline for green buttons).
This can be an accessibility issue because some focus outlines don't have sufficient contrast and also look inconsistent which makes keyboard navigation harder.

## Storefront accessibility: Deprecated current structure of `filter-active` span elements in favor of a button:
Currently, the label that displays an activate listing filter is using a span element with custom styling. 
Instead, we will use a Bootstrap button `.btn` which also functions as the "Remove filter" button directly.
This improves the focus outline visibility of active filters and also increases the click-surface for removal.
The `getLabelTemplate` of the `ListingPlugin` will return the updated HTML structure.

Current HTML structure:
```html
<span class="filter-active">
    <span aria-hidden="true">Example manufacturer</span>
    <button class="filter-active-remove" data-id="1234" aria-label="Remove filter: Example manufacturer">
        &times;
    </button>
</span>
```

New HTML structure:
```html
<button class="filter-active btn" data-id="1234" aria-label="Remove filter: Example manufacturer">
    Example manufacturer
    <span aria-hidden="true" class="ms-1 fs-4">&times;</span>
</button>
```
___
# Next Major Version Changes
## Storefront accessibility: The `filter-active` span element is changed to a button:
* The `filter-active` element that displays an active listing filter is changed from `<span>` to `<button>`.
* The `filter-active` and `filter-reset-all` are now Bootstrap buttons using `--bs-btn-*` variables.
* The method `getLabelTemplate` of JS-plugin `ListingPlugin` will return the updated HTML-structure.
* The option `activeFilterLabelClass` of JS-plugin `ListingPlugin` is removed. Use `activeFilterLabelClasses` to render the label classes and `activeFilterLabelSelector` as the selector for events.

If you are overriding `getLabelTemplate` of JS-plugin `ListingPlugin`, the new structure should be considered:

change
```js 
class MyListing extends Listing {
    getLabelTemplate(label) {
        return `
        <span class="${this.options.activeFilterLabelClass}" data-my-extra-attr="something">
            ${this.getLabelPreviewTemplate(label)}
            <span aria-hidden="true">${label.label}</span>
            <button class="${this.options.activeFilterLabelRemoveClass}"
                    data-id="${label.id}"
                    aria-label="${this.options.snippets.removeFilterAriaLabel}: ${label.label}">
                &times;
            </button>
        </span>
        `;
    }
}
```
to
```js
class MyListing extends Listing {
    getLabelTemplate(label) {
        return `
        <button 
            data-my-extra-attr="something"
            class="${this.options.activeFilterLabelClasses}" <!-- Use activeFilterLabelClasses to render the classes -->
            data-id="${label.id}"
            aria-label="${this.options.snippets.removeFilterAriaLabel}: ${label.label}">
            ${this.getLabelPreviewTemplate(label)}
            ${label.label}
            <span aria-hidden="true" class="ms-1 fs-4">&times;</span> <!-- The activeFilterLabelRemoveClass is removed because no special styling is needed. -->
        </button>
        `;
    }
}
```