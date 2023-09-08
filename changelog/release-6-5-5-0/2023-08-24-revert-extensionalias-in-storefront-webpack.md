---
title: Revert extensionAlias in Storefront webpack config
issue: NEXT-29901
---
# Storefront
* Removed `extensionAlias` for TypeScript support in `webpack.config.js` because it conflicts with existing app/plugin aliases to NPM package names with suffix `.js`
