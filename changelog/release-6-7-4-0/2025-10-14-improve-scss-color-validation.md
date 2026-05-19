---
title: Improve SCSS color validation
issue: #12947
---
# Storefront
* Changed `Shopware\Storefront\Theme\Validator\SCSSValidator` to allow more options for `rgb()` and `hsl()` color functions.
    * RGB and RGBA with hex colors and SCSS variables:*
        * `rgb($primary / 0.5)`
        * `rgb(#fff / 0.5)`
        * `rgba($primary, 0.5)`
        * `rgba(#fff, 0.5)`
    * RGB and RGBA in combination with SCSS functions:
        * `rgb(red($primary), green($primary), blue($primary))`
        * `rgba(red($primary), green($primary), blue($primary), 0.5)`
        * `rgba(red($primary), green($primary), blue($primary), alpha($primary))`
    * HSL and HSLA in combination with SCSS functions:
        * `hsl(hue($primary), saturation($primary), 94%)`
        * `hsl(180deg saturation($primary) lightness($primary)`
        * `hsla(hue($primary), saturation($primary), lightness($primary), alpha($primary))`