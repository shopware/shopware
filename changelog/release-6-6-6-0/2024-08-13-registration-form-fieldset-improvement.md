---
title: Improved registration form accessibility
issue: NEXT-33689
---
# Storefront
* Added new block `component_account_register_personal_legend` to `register.html.twig`.
* Added new legend element with class `register-personal-title` to register form in `register.html.twig` which is only visible to screen readers.
* Added new label `registerPersonalLegend` for the legend of the personal information fieldset.
* Changed form sections in `register.html.twig` from `div` to `fieldset`.
* Changed card titles of form sections from `div` to `legend`.
* Changed the font-size of `form-text` to use `rem` instead of the Bootstrap default value which is based on `em`.
