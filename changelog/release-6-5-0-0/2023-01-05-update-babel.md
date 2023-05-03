---
title: Update babel and browserslist
issue: NEXT-22226
---
# Storefront
* Changed `@babel/cli` to version `7.20.7`
* Changed `@babel/core` to version `7.20.12`
* Changed `@babel/preset-env` to version `7.20.2`
* Added package `core-js` with version `3.27.1`
* Changed filename of `.browserlistrc` to `.browserslistrc` to fix a typo and make it considered by babel
* Changed supported browsers in `.browserslistrc` to be aligned with Bootstrap 5.2 browser support, see: https://getbootstrap.com/docs/5.2/getting-started/browsers-devices/
* Removed deprecated package `@babel/polyfill` in favor of importing `core-js` directly, see: https://babeljs.io/docs/en/babel-polyfill
* Removed babel plugin `@babel/plugin-proposal-class-properties` because it is now included in `@babel/preset-env`, see: https://babeljs.io/docs/en/babel-plugin-proposal-class-properties
* Removed babel plugin `@babel/plugin-transform-object-assign` because `Object.assign` is has full support in all browsers listed in `.browserslistrc`, see: https://caniuse.com/mdn-javascript_builtins_object_assign
