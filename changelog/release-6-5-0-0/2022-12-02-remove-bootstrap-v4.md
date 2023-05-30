---
title: Remove Bootstrap v4
issue: NEXT-23944
---
# Storefront
* Removed deprecated NPM package Bootstrap `4.3.1` in favor of Bootstrap `5.2.2`
* Removed deprecated NPM package `jquery` and all its usages
* Removed deprecated class `Resources/app/storefront/src/utility/tooltip/tooltip.util.js` , use `Resources/app/storefront/src/utility/bootstrap/bootstrap.util.js` instead
* Removed deprecated SCSS file `Resources/app/storefront/src/scss/base/_ie11-fixes.scss` without replacement because IE11 support is discontinued
* Removed deprecated global Twig variables in favor of new Bootstrap v5 classes in `\Shopware\Storefront\Framework\Twig\TemplateDataExtension`
    * Removed `dataBsToggleAttr`, use `data-bs-toggle` instead
    * Removed `dataBsDismissAttr`, use `data-bs-dismiss` instead
    * Removed `dataBsTargetAttr`, use `data-bs-target` instead
    * Removed `dataBsOffsetAttr`, use `data-bs-offset` instead
    * Removed `formSelectClass`, use `form-select` instead
    * Removed `gridNoGuttersClass`, use `g-0` instead
    * Removed `formCheckboxWrapperClass`, use `form-check` instead
    * Removed `formSwitchWrapperClass`, use `form-check form-switch` instead
    * Removed `formRadioWrapperClass`, use `form-check` instead
    * Removed `formCheckInputClass`, use `form-check-input` instead
    * Removed `formCheckLabelClass`, use `form-check-label` instead
    * Removed `formRowClass`, use `row g-2` instead
    * Removed `modalCloseBtnClass`, use `btn-close` instead
    * Removed `visuallyHiddenClass`, use `visually-hidden` instead
    * Removed `floatStartClass`, use `float-start` instead
    * Removed `floatEndClass`, use `float-end` instead
    * Removed `bgClass`, use `bg` instead
    * Removed `paddingStartClass`, use `ps-*` instead
    * Removed `paddingEndClass`, use `pe-*` instead
    * Removed `marginStartClass`, use `ms-*` instead
    * Removed `marginEndClass`, use `me-*` instead
