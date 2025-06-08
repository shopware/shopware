---
title: Improved text formatting in the storefront
issue: NEXT-33807
---
# Storefront
* Deprecated several text styling variables and properties which will be changed with the next major update. You can get the new styling by activating the `ACCESSIBILITY_TWEAKS` flag.
  * `$font-size-base` will be changed from `0.875rem` to `1rem`.
  * `$font-size-lg` will be changed from `1rem` to `1.125rem`.
  * `$font-size-sm` will be changed from `0.75rem` to `0.875rem`.
  * `$paragraph-margin-bottom` will be changed from `1rem` to `2rem`.
  * `line-height` of `.quantity-selector-group-input` will be changed to `1rem`.
  * `font-size` of `.form-text` will be changed from `0.875rem` to `$font-size-base`.
  * `font-size` of `.account-profile-change` will be changed from `$font-size-sm` to `$font-size-base`.
  * `font-size` of `.product-description` will be changed from `0.875rem` to `$font-size-base`.
  * `line-height` of `.product-description` will be changed from `1.125rem` to `$line-height-base`.
  * `height` of `.product-description` will be changed from `3.375rem` to `4.5rem`.
  * `line-height` of `.quantity-selector-group-input` will be changed to `1rem`.
  * `font-size` of `.main-navigation-menu` will be changed from `$font-size-lg` to `$font-size-base`.
  * `font-size` of `.navigation-flyout-category-link` will be changed from `$font-size-lg` to `$font-size-base`.
* Added `margin-bottom` to `ol`, `ul`, and `dl` with the value `$paragraph-margin-bottom`.
___
# Upgrade information  
## Accessibility - Storefront base font-size  
In regard to better readability we decided to update the base font-size of the storefront to the browser standard of `1rem` (16px). Other values will be adjusted accordingly to match. This change will be introduced with the next major release. You can already activate the changes together with other accessibility improvements by using the `ACCESSIBILITY_TWEAKS` flag.
___
# Next Major Version Changes
## Accessibility - Storefront base font-size  
In regard to better readability the base font-size of the storefront is updated to the browser standard of `1rem` (16px). Other text formatting is adjusted accordingly. The following variables and properties are changed:

* `$font-size-base` changed from `0.875rem` to `1rem`.
* `$font-size-lg` changed from `1rem` to `1.125rem`.
* `$font-size-sm` changed from `0.75rem` to `0.875rem`.
* `$paragraph-margin-bottom` changed from `1rem` to `2rem`.
* `line-height` of `.quantity-selector-group-input` changed to `1rem`.
* `font-size` of `.form-text` changed from `0.875rem` to `$font-size-base`.
* `font-size` of `.account-profile-change` changed from `$font-size-sm` to `$font-size-base`.
* `font-size` of `.product-description` changed from `0.875rem` to `$font-size-base`.
* `line-height` of `.product-description` changed from `1.125rem` to `$line-height-base`.
* `height` of `.product-description` changed from `3.375rem` to `4.5rem`.
* `line-height` of `.quantity-selector-group-input` changed to `1rem`.
* `font-size` of `.main-navigation-menu` changed from `$font-size-lg` to `$font-size-base`.
* `font-size` of `.navigation-flyout-category-link`changed from `$font-size-lg` to `$font-size-base`.
