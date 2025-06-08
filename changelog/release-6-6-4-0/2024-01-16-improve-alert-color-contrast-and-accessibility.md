---
title: Improve alert color contrast and accessibility
issue: NEXT-26302
---
# Storefront
* Deprecated SASS mixin `sw-skin-alert`. We generate contextual modifier classes (`.alert-*`) directly inside a SASS loop instead like documented by Bootstrap.
* Deprecated CSS class `alert-has-icon`. Will be removed. Use helper classes `d-flex align-items-center` directly in the template like documented by Bootstrap.
* Deprecated inner alert container `alert-content` inside `Resources/views/storefront/utilities/alert.html.twig`. Will be removed because it is not needed.
* Deprecated default value of `$alert-border-width`. Default will be `1px`.
* Deprecated default value of `$alert-padding-x`. Default will be `0.5rem`.
* Deprecated default value of `$alert-padding-y`. Default will be `0.5rem`.
* Changed default color value `sw-color-brand-primary` to `#0042a0`.
* Changed default color value `sw-color-buy-button` to `#0042a0`.
* Changed default color value `sw-color-brand-secondary` to `#474a57`.
* Changed default color value `sw-color-success` to `#007e4e`.
* Changed default color value `sw-color-info` to `#005b99`.
* Changed default color value `sw-color-warning` to `#974200`.
* Changed default color value `sw-color-danger` to `#c20017`.

___
# Next Major Version Changes
## Removal of Storefront `sw-skin-alert` SCSS mixin
The mixin `sw-skin-alert` will be removed in v6.7.0. Instead of styling the alert manually with CSS selectors and the custom mixin `sw-skin-alert`,
we modify the appearance inside the `alert-*` modifier classes directly with the Bootstrap CSS variables like it is documented: https://getbootstrap.com/docs/5.3/components/alerts/#sass-loops

Before:
```scss
@each $color, $value in $theme-colors {
  .alert-#{$color} {
    @include sw-skin-alert($value, $white);
  }
}
```

After:
```scss
@each $state, $value in $theme-colors {
  .alert-#{$state} {
    --#{$prefix}alert-border-color: #{$value};
    --#{$prefix}alert-bg: #{$white};
    --#{$prefix}alert-color: #{$body-color};
  }
}
```

## Removal of Storefront alert class `alert-has-icon` styling
When rendering an alert using the include template `Resources/views/storefront/utilities/alert.html.twig`, the class `alert-has-icon` will be removed. Helper classes `d-flex align-items-center` will be used instead.

```diff
- <div class="alert alert-info alert-has-icon">
+ <div class="alert alert-info d-flex align-items-center">
    {% sw_icon 'info' %}
    <div class="alert-content-container">
        An important info
    </div>
</div>
```

## Removal of Storefront alert inner container `alert-content`
As of v6.7.0, the superfluous inner container `alert-content` will be removed to have lesser elements and be more aligned with Bootstraps alert structure.
When rendering an alert using the include template `Resources/views/storefront/utilities/alert.html.twig`, the inner container `alert-content` will no longer be present in the HTML output.

The general usage of `Resources/views/storefront/utilities/alert.html.twig` and all include parameters remain the same.

Before:
```html
<div role="alert" class="alert alert-info d-flex align-items-center">
    <span class="icon icon-info"><svg></svg></span>                                                    
    <div class="alert-content-container">
        <div class="alert-content">                                                    
            Your shopping cart is empty.
        </div>                
    </div>
</div>
```

After:
```html
<div role="alert" class="alert alert-info d-flex align-items-center">
    <span class="icon icon-info"><svg></svg></span>                                                    
    <div class="alert-content-container">
        Your shopping cart is empty.
    </div>
</div>
```
