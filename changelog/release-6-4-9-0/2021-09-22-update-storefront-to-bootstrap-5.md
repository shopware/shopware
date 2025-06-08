---
title: Update Storefront to Bootstrap 5
issue: NEXT-15229
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Storefront
* Added additional npm dependency `bootstrap5` with version `5.1.3`
* Added npm dependency `@popperjs/core` with version `2.10.1`
* Added new block `layout_head_javascript_jquery` in `Resources/views/storefront/layout/meta.html.twig`
* Added new block `base_script_jquery` in `Resources/views/storefront/base.html.twig`
* Added global twig variables in `\Shopware\Storefront\Framework\Twig\TemplateDataExtension::getGlobals`
    * Added variable `dataBsToggleAttr` to replace `data-toggle` with `data-bs-toggle`
    * Added variable `dataBsDismissAttr` to replace `data-dismiss` with `data-bs-dismiss`
    * Added variable `dataBsTargetAttr` to replace `data-target` with `data-bs-target`
    * Added variable `dataBsOffsetAttr` to replace `data-offset` with `data-bs-offset`
    * Added variable `formSelectClass` to replace `custom-select` with `form-select`
    * Added variable `gridNoGuttersClass` to replace `no-gutters` with `g-0`
    * Added variable `formCheckboxWrapperClass` to replace `custom-control custom-checkbox` with `form-check`
    * Added variable `formRadioWrapperClass` to replace `custom-control custom-radio` with `form-check`
    * Added variable `formCheckInputClass` to replace `custom-control-input` with `form-check-input`
    * Added variable `formCheckLabelClass` to replace `custom-control-label` with `form-check-label`
    * Added variable `formRowClass` to replace `form-row` with `row g-2`
    * Added variable `modalCloseBtnClass` to replace `modal-close` with `'btn-close`
    * Added variable `visuallyHiddenClass` to replace `sr-only` with `visually-hidden`
    * Added variable `floatStartClass` to replace `float-left` with `float-start`
    * Added variable `floatEndClass` to replace `float-right` with `float-end`
    * Added variable `bgClass` to replace `badge` with `bg`
* Deprecated Bootstrap class `btn-block` in the following Twig templates
    * `Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
    * `Resources/views/storefront/component/listing/filter/filter-multi-select.html.twig`
    * `Resources/views/storefront/component/listing/filter/filter-range.html.twig`
    * `Resources/views/storefront/component/product/card/action.html.twig`
    * `Resources/views/storefront/component/product/card/action.html.twig`
    * `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * `Resources/views/storefront/page/checkout/confirm/index.html.twig`
    * `Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
* Deprecated wrapper element `input-group-append` in the following Twig templates:
    * `Resources/views/storefront/layout/header/search.html.twig`
    * `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * `Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
* Added new breakpoint `xxl` to `\Shopware\Storefront\Theme\ThemeConfigValueAccessor::getThemeConfig`
* Added new breakpoint `xxl` to twig variable `breakpoint` in `Resources/views/storefront/base.html.twig`
* Added new breakpoint `xxl` to twig variable `gallerySliderOptions` in `Resources/views/storefront/element/cms-element-image-gallery.html.twig`
* Added new static `isXXL` in `viewport-detection.helper.js`
* Added new event dispatch with `Viewport/isXXL` in `viewport-detection.helper.js`
* Added new breakpoint `xxl` to default `options` of `base-slider.plugin.js`
* Added new breakpoint `xxl` to default `options` of `gallery-slider.plugin.js`
* Deprecated all internal jQuery usages in JavaScript plugins, utils and other classes
    * `address-editor.plugin.js`
    * `collapse-checkout-confirm-methods.plugin.js`
    * `collapse-footer-columns.plugin.js`
    * `cross-selling.plugin.js`
        * Deprecated default option `tabSelector` with value `a[data-toggle="tab"]`
    * `fading.plugin.js`
    * `zoom-modal.plugin.js`
    * `element-loading-indicator.util.js`
        * Added new const `VISUALLY_HIDDEN_CLASS`
    * `loading-indicator.util.js`
        * Added new const `VISUALLY_HIDDEN_CLASS`
    * `ajax-modal-extension.util.js`
        * Added new const `MODAL_TRIGGER_DATA_ATTRIBUTE`
    * `pseudo-modal.util.js`
* Added new default SCSS variable overwrite `$link-decoration: none !default;` in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss`
* Added new default SCSS variable overwrite `$link-hover-decoration: underline !default;` in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss`
* Deprecated CSS class `.modal-close` in `Resources/app/storefront/src/scss/component/_zoom-modal.scss`
* Deprecated CSS class `.input-group-append` in `Resources/app/storefront/src/scss/skin/shopware/layout/_header.scss`
* Deprecated usages of the next breakpoint for mixin call `media-breakpoint-down()` in the following SCSS files:
    * `Resources/app/storefront/src/scss/component/_base-slider.scss`
    * `Resources/app/storefront/src/scss/component/_cms-block.scss`
    * `Resources/app/storefront/src/scss/component/_cms-element.scss`
    * `Resources/app/storefront/src/scss/component/_cms-form-confirmation.scss`
    * `Resources/app/storefront/src/scss/component/_cms-sections.scss`
    * `Resources/app/storefront/src/scss/component/_filter-panel.scss`
    * `Resources/app/storefront/src/scss/component/_gallery-slider.scss`
    * `Resources/app/storefront/src/scss/layout/_offcanvas-cart.scss`
    * `Resources/app/storefront/src/scss/page/account/_account.scss`
    * `Resources/app/storefront/src/scss/page/account/_edit-order.scss`
    * `Resources/app/storefront/src/scss/page/account/_order.scss`
    * `Resources/app/storefront/src/scss/page/account/_order-detail.scss`
    * `Resources/app/storefront/src/scss/page/wishlist/_wishlist.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/account/_order.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/account/_order-detail.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/checkout/_cart.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/product-detail/_cross-selling.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/product-detail/_product-detail.scss`
    * `Resources/app/storefront/src/scss/skin/shopware/page/product-detail/_tabs.scss`
___
# Upgrade Information

## Bootstrap v5 preview

We want to update the Storefront to Bootstrap v5 in the next major release of Shopware.
Because Bootstrap v5 introduces breaking changes when updating from Bootstrap v4, we have implemented the update behind a feature flag.
This gives you the possibility to test Bootstrap v5 with your apps or themes before the next major release. The current Bootstrap v4 implementation is still the default.
With the next major release Bootstrap v5 will be the default.

**The Bootstrap v5 preview should not be used in production environments because it is still under development!**

## What happens when updating to Bootstrap v5?

* Dropped jQuery dependency (It can be added manually if needed, see "Still need jQuery?")
* Dropped Internet Explorer 10 and 11
* Dropped Microsoft Edge < 16 (Legacy Edge)
* Dropped Firefox < 60
* Dropped Safari < 12
* Dropped iOS Safari < 12
* Dropped Chrome < 60

You can find a full migration guide on the official Bootstrap website: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)

## Activate Bootstrap v5

* Activate the next major feature flag `V6_5_0_0` in your .env or .psh.override.yaml
* Re-build the storefront using `psh.phar storefront:build`
* During the build process webpack will show a warning that Bootstrap v5 is being used
* If the Bootstrap v5 resources are not build, please try running `bin/console feature:dump` and try again

## How to consider Bootstrap v5

Because of the breaking changes inside Bootstrap v5 you will find several places with backward-compatibility code in the Shopware platform.
This code is being used to already provide the Bootstrap v5 implementation while keeping the Bootstrap v4 implementation for backward-compatibility.
Depending, if you are an app/theme developer or a platform contributor you need to adapt the backward-compatibility for your use case.

* **For platform contributors**: Use feature flag conditions.<br>
  Please use feature flag conditions with flag `V6_5_0_0` to migrate to Bootstrap v5 functionality while keeping the Bootstrap v4 implementations for backward-compatibility.
* **For app/plugin/theme developers**: Migrate your code directly to Bootstrap v5 or use feature flag conditions.<br>
  * Option A: You can migrate your code directly to Bootstrap v5 by creating a separate git branch and preparing a new release version with the breaking changes. 
    The major feature flag `V6_5_0_0` can be used to activate Bootstrap v5 during development.
  * Option B: Depending on you app/plugin/theme it might be more feasible to you to use the major feature flag `V6_5_0_0` to create compatibility to Bootstrap v5. 
    You can use feature flag conditions with `V6_5_0_0` inside your app/plugin/theme. 
    The major feature flag `V6_5_0_0` will remain inside the shopware platform and will be `true` by default after the release of `v6.5.0.0`

You can find some code examples below which will illustrate this. There are always three examples for the same code snippet:

1. Bootstrap v4 (Current implementation) - How it looks right now
2. Bootstrap v5 with backward-compatibility (for platform contributors or app/plugin/theme developers)
3. Bootstrap v5 next major - How it will look after the release of v6.5.0.0 (for app/plugin/theme developers)

**Please beware that this is only needed for areas which are effected by braking changes from Bootstrap v5. See: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)**

### HTML/Twig

#### 1. Bootstrap v4 (Current implementation):
```html
<button class="collapsed btn"
        data-toggle="collapse"
        data-target="#target-selector">
    Collapse button
</button>

<a href="#" class="btn btn-block">Default button</a>
```

#### 2. Bootstrap v5 with backward-compatibility (for platform contributors or app/plugin/theme developers):

**Attention:** There are a good amount of attributes and classes which have been renamed inside Bootstrap v5.
To avoid having too many `{% if %}` conditions in the template we have created global twig variables for attribute renaming.

```html
{# Use global twig variable `dataBsToggleAttr` to toggle between `data-toggle` and `data-bs-toggle`: #}
<button class="collapsed btn"
        {{ dataBsToggleAttr }}="collapse"
        {{ dataBsTargetAttr }}="#target-selector">
    Collapse button
</button>

{# For larger markup changes use regular feature conditions: #}

{# @deprecated tag:v6.5.0 - Bootstrap v5 removes `btn-block` class, use `d-grid` wrapper instead #}
{% if feature('v6.5.0.0') %}
    <div class="d-grid">
        <a href="#" class="btn">Default button</a>
    </div>
{% else %}
    <a href="#" class="btn btn-block">Default button</a>
{% endif %}
```

#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```html
<button class="collapsed btn"
        data-bs-toggle="collapse"
        data-bs-target="#target-selector">
    Collapse button
</button>

<div class="d-grid">
    <a href="#" class="btn">Default button</a>
</div>
```

### SCSS

#### 1. Bootstrap v4 (Current implementation):
```scss
.page-link {
    line-height: $custom-select-line-height;
}
```
#### 2. Bootstrap v5 with backward-compatibility (for platform contributors or app/plugin/theme developers):

Attention:
```scss
.page-link {
    // @deprecated tag:v6.5.0 - Bootstrap v5 renames variable $custom-select-line-height to $form-select-line-height
    @if feature('V6_5_0_0') {
        line-height: $form-select-line-height;
    } @else {
        line-height: $custom-select-line-height;
    }
}
```

#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```scss
.page-link {
    line-height: $form-select-line-height;
}
```

### JavaScript

#### 1. Bootstrap v4 (Current implementation):
```js
$(collapse).collapse('toggle');
```

#### 2. Bootstrap v5 with backward-compatibility (for platform contributors or app/plugin/theme developers):
```js
// Use feature.helper to check for feature flags.
import Feature from 'src/helper/feature.helper';

/** @deprecated tag:v6.5.0 - Bootstrap v5 uses native HTML elements to init Collapse plugin */
if (Feature.isActive('V6_5_0_0')) {
    new bootstrap.Collapse(collapse, {
        toggle: true,
    });
} else {
    $(collapse).collapse('toggle');
}
```

#### 3. Bootstrap v5 next major (for app/plugin/theme developers):
```js
new bootstrap.Collapse(collapse, {
    toggle: true,
});
```

## Known issues

Since Bootstrap v5 is still behind the next major feature flag `V6_5_0_0` it is possible that issues occur.
The following list contains issues that we are aware of. We want to address this issues before the next major version.

* **Styling**<br>
  There might be smaller styling issues here and there. Mostly spacing or slightly wrong colors.
* **Bootstrap v5 OffCanvas**<br>
  Bootstrap v5 ships its own OffCanvas component. Shopware is still using its custom OffCanvas at the moment.
  It is planned to migrate the Shopware OffCanvas to the Bootstrap OffCanvas.
* **Modifying SCSS $theme-colors**<br>
  Currently it is not possible to add or remove custom colors to $theme-colors like it is described in the [Bootstrap documentation](https://getbootstrap.com/docs/5.1/customize/sass/#add-to-map).

___
# Next Major Version Changes

Bootstrap v5 introduces breaking changes in HTML, (S)CSS and JavaScript.
Below you can find a migration overview of the effected areas in the Shopware platform.
Please consider that we cannot provide code migration examples for every possible scenario of a UI-Framework like Bootstrap.
You can find a full migration guide on the official Bootstrap website: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)

## HTML/Twig

The Update to Bootstrap v5 often contains the renaming of attributes and classes. Those need to be replaced.
However, all Twig blocks remain untouched so all template extensions will take effect.

### Rename attributes and classes

* Replace `data-toggle` with `data-bs-toggle`
* Replace `data-dismiss` with `data-bs-dismiss`
* Replace `data-target` with `data-bs-target`
* Replace `data-offset` with `data-bs-offset`
* Replace `custom-select` with `form-select`
* Replace `custom-file` with `form-file`
* Replace `custom-range` with `form-range`
* Replace `no-gutters` with `g-0`
* Replace `custom-control custom-checkbox` with `form-check`
* Replace `custom-control custom-switch` with `form-check form-switch`
* Replace `custom-control custom-radio` with `form-check`
* Replace `custom-control-input` with `form-check-input`
* Replace `custom-control-label` with `form-check-label`
* Replace `form-row` with `row g-2`
* Replace `modal-close` with `btn-close`
* Replace `sr-only` with `visually-hidden`
* Replace `badge-*` with `bg-*`
* Replace `badge-pill` with `rounded-pill`
* Replace `close` with `btn-close`
* Replace `left-*` and `right-*` with `start-*` and `end-*`
* Replace `float-left` and `float-right` with `float-start` and `float-end`.
* Replace `border-left` and `border-right` with `border-start` and `border-end`.
* Replace `rounded-left` and `rounded-right` with `rounded-start` and `rounded-end`.
* Replace `ml-*` and `mr-*` with `ms-*` and `me-*`.
* Replace `pl-*` and `pr-*` with `ps-*` and `pe-*`.
* Replace `text-left` and `text-right` with `text-start` and `text-end`.

### Replace .btn-block class with .d-grid wrapper

#### Before

```html
<a href="#" class="btn btn-block">Default button</a>
```

#### After

```html
<div class="d-grid">
    <a href="#" class="btn">Default button</a>
</div>
```

### Remove .input-group-append wrapper inside .input-group

#### Before

```html
<div class="input-group">
    <input type="text" class="form-control">
    <div class="input-group-append">
        <button type="submit" class="btn">Submit</button>
    </div>
</div>
```

#### After

```html
<div class="input-group">
    <input type="text" class="form-control">
    <button type="submit" class="btn">Submit</button>
</div>
```

## SCSS

Please consider that the classes documented in "HTML/Twig" must also be replaced inside SCSS.

* Replace all mixin usages of `media-breakpoint-down()` with the current breakpoint, instead of the next breakpoint:
    * Replace `media-breakpoint-down(xs)` with `media-breakpoint-down(sm)`
    * Replace `media-breakpoint-down(sm)` with `media-breakpoint-down(md)`
    * Replace `media-breakpoint-down(md)` with `media-breakpoint-down(lg)`
    * Replace `media-breakpoint-down(lg)` with `media-breakpoint-down(xl)`
    * Replace `media-breakpoint-down(xl)` with `media-breakpoint-down(xxl)`
* Replace `$custom-select-*` variable with `$form-select-*`

## JavaScript/jQuery

With the update to Bootstrap v5, the jQuery dependency will be removed from the shopware platform.
We strongly recommend migrating jQuery implementations to Vanilla JavaScript.

### Initializing Bootstrap JavaScript plugins

#### Before

```js
// Previously Bootstrap plugins were initialized on jQuery elements
const collapse = DomAccess.querySelector('.collapse');
$(collapse).collapse('toggle');
```

#### After

```js
// With Bootstrap v5 the Collapse plugin is instantiated and takes a native HTML element as a parameter
const collapse = DomAccess.querySelector('.collapse');
new bootstrap.Collapse(collapse, {
    toggle: true,
});
```

### Subscribing to Bootstrap JavaScript events

#### Before

```js
// Previously Bootstrap events were subscribed using the jQuery `on()` method
const collapse = DomAccess.querySelector('.collapse');
$(collapse).on('show.bs.collapse', this._myMethod.bind(this));
$(collapse).on('hide.bs.collapse', this._myMethod.bind(this));
```

#### After

```js
// With Bootstrap v5 a native event listener is being used
const collapse = DomAccess.querySelector('.collapse');
collapse.addEventListener('show.bs.collapse', this._myMethod.bind(this));
collapse.addEventListener('hide.bs.collapse', this._myMethod.bind(this));
```

### Still need jQuery?

In case you still need jQuery, you can add it to your own app or theme.
This is the recommended method for all apps/themes which do not have control over the Shopware environment in which they are running in.

* Extend the file `platform/src/Storefront/Resources/views/storefront/layout/meta.html.twig`.
* Use the block `layout_head_javascript_jquery` to add a `<script>` tag containing jQuery. **Only use this block to add jQuery**.
* This block is not deprecated and can be used in the long term beyond the next major version of shopware.
* Do **not** use the `{{ parent() }}` call. This prevents multiple usages of jQuery. Even if multiple other plugins/apps use this method, the jQuery script will only be added once.
* Please use jQuery version `3.5.1` (slim minified) to avoid compatibility issues between different plugins/apps.
* If you don't want to use a CDN for jQuery, [download jQuery from the official website](https://releases.jquery.com/jquery/) (jQuery Core 3.5.1 - slim minified) and add it to `MyExtension/src/Resources/app/storefront/src/assets/jquery-3.5.1.slim.min.js`
* After executing `bin/console asset:install`, you can reference the file using the `assset()` function. See also: https://developer.shopware.com/docs/guides/plugins/plugins/storefront/add-custom-assets

```html
{% sw_extends '@Storefront/storefront/layout/meta.html.twig' %}

{% block layout_head_javascript_jquery %}
    <script src="{{ asset('bundles/myextension/assets/jquery-3.5.1.slim.min.js', 'asset') }}"></script>
{% endblock %}
```

**Attention:** If you need to test jQuery prior to the next major version, you must use the block `base_script_jquery` inside `platform/src/Storefront/Resources/views/storefront/base.html.twig`, instead.
The block `base_script_jquery` will be moved to `layout/meta.html.twig` with the next major version. However, the purpose of the block remains the same:

```html
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_script_jquery %}
    <script src="{{ asset('bundles/myextension/assets/jquery-3.5.1.slim.min.js', 'asset') }}"></script>
{% endblock %}
```
