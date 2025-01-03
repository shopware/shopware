---
title: Fix webpack plugin config order
issue: NEXT-40038
author: Robert Bisovski
author_github: @ROBJkE
---
# Storefront
* Changed `src/Storefront/Resources/app/storefront/webpack.config.js` to fix the order of the webpack plugin configuration. This ensures that configurations from parent themes are not skipped.
