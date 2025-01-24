---
title: Update polyfills and cleanup JS
issue: #0000
---
# Storefront
* Removed NPM package `query-string`, using native `URLSearchParams` instead.
* Removed NPM package `teser-webpack-plugin`. Minification is already handled by `swc-loader`.
___
# Next Major Version Changes
## Update Storefront JS polyfills and browser support:
* TODO: guide "defaults"
* TODO: Guide to override polyfills if wanted
## Remove query-string package:
* TODO: Terser, query-string