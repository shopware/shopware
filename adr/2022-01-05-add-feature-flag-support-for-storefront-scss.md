---
title: Add feature flag support for Storefront SCSS
date: 2022-01-05
area: storefront
tags: [feature-flag, scss]
---

## Context

* There is no possibility to check for feature flags inside the Storefront SCSS.
* Altering the SCSS depending on a feature flag will require workarounds like e.g. "making up" and additional class in the template and use the feature toggle in twig instead.
  * The SCSS of a selector which is hidden behind a feature flag will still be in the compiled CSS.
* It is not easily possible to make breaking changes inside SCSS functions, mixins or variables backward-compatible with the use of feature flags.

## Decision

* Add the possibility to check for feature flags inside SCSS, similar to the twig implementation.
* The feature configuration from `Feature::getAll()` is converted to a SCSS map inside `\Shopware\Storefront\Theme\ThemeCompiler::getFeatureConfigScssMap`.
  * This SCSS map is always added to the SCSS string which gets processed by `\Shopware\Storefront\Theme\ThemeCompiler::compileTheme`.
  * For webpack hot-proxy the `var/config_js_features.json` is used instead.
* The SCSS map looks like this: `$sw-features: ("FEATURE_NEXT_1234": false, "FEATURE_NEXT_1235": true);`
  * See https://sass-lang.com/documentation/values/maps
* A globally available function `feature()` is used to read inside the SCSS map if a desired feature is active.

Example:

```scss
body {
    @if feature('FEATURE_NEXT_1') {
        background-color: #ff0000;
    } @else {
        background-color: #ffcc00;
    }
}
```

## Consequences

The feature dump file `var/config_js_features.json` is now used by the Storefront webpack configuration `src/Storefront/Resources/app/storefront/webpack.config.js`.
When the feature dump cannot be found, all features will be disabled/false inside `webpack.config.js` and hot-proxy SCSS. 
A warning is shown in this case with the request to execute `bin/console feature:dump` manually.
