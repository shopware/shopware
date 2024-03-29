---
title: Remove unneeded NPM packages
issue: NEXT-31820
---
# Storefront
* Removed NPM package `@shopware-ag/webpack-plugin-injector` because it is unused. Injecting plugins/apps was moved inside `webpack.config.js`.
* Removed NPM package `axios` because it was only used for lighthouse tests and is not needed for the production build of the Storefront. Axios request was replaced with native fetch API.
* Removed NPM package `are-you-es5` because it was used very rarely and is not needed for the production build of the Storefront.
* Removed NPM script `check-modules` that executed `are-you-es5`.
* Changed NPM package `lodash` to `lodash.get` to download less modules because we only use `_get` from `lodash`.
