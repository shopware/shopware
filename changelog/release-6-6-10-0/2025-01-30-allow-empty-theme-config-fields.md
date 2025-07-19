---
title: Allow empty fields in theme config
issue: NEXT-40385
---
# Storefront
* Changed `dumpVariables()` and `formatVariables()` in `\Shopware\Storefront\Theme\ThemeCompiler` to write empty theme config fields as a "null" value in SCSS
* Changed the `validate()` method in `\Shopware\Storefront\Theme\Validator\SCSSValidator` to allow empty values.
___
# Upgrade Information
## Empty theme config values
We changed the way empty theme config fields are handled. Previous the fields were not added as variables to the SCSS if they were empty, which could lead to unwanted compiler crashes. But empty values could be a reasonable setting, for example to use it for optional styling or the usage of default variables in the SCSS code. Therefore, we decided to always add theme config fields to the SCSS, even if they are empty. In that case the value of the variable is set to "null". This is a valid value in SCSS and works along default variables or conditional styling.

### Example: Default Variables
```SCSS
$test-color: #fff !default;

body {
    background: $test-color;
}
```
If the variable is left empty in the config, the default value will be used.

### Example: Conditions
```SCSS
@if ($test-color != null) {
    body {
        background: darken($test-color, 20);
    }
}
```
You can use a condition to do optional styling. It should also be used in case of color variables and the usage of color functions. Those color functions would break with a null value if you don't use a proper default value.
