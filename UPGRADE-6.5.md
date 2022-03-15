# 6.5.0.0
## Introduced in 6.4.9.0
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
* The function `translatedTypes` in `src/app/component/rule/sw-condition-type-select/index.js` is removed. Use `translatedLabel` property of conditions.
```

## Introduced in 6.4.8.0
The whole namespace `Shopware\Core\Framework\Changelog` was marked `@internal` and is no longer part of the BC-Promise. Please move to a different changelog generator vendor.

