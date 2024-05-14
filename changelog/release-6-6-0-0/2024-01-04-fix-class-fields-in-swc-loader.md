---
title: Fix class fields in swc-loader
issue: NEXT-32772
---
# Storefront
* Changed location of `.browserslistrc` file from `src/Storefront/Resources/.browserslistrc` to `src/Storefront/Resources/app/storefront.browserslistrc` so it is located in the frontend stack.
* Added property `useDefineForClassFields` with `false` to `swc-loader` inside `webpack.config.js` in order to restore the previous babel behaviour.
