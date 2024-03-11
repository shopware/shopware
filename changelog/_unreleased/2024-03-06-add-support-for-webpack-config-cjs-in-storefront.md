---
title: Add support for webpack.config.cjs in storefront
issue: NEXT-34184
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Storefront
* Added possibility to use ESM packages in Storefront js. An additional Webpack config must still be in common js to be required in the build process. Added support to require `webpack.config.cjs` from your package. 
