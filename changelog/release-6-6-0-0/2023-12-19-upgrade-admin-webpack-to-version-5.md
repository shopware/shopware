---
title: Upgrade Admin webpack to version 5
issue: NEXT-30952
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `webpack` version from `4` to `5`
___
# Upgrade Information
## Webpack API changes
If your plugin uses a custom webpack configuration, you need to update the configuration to the new Webpack 5 API.
Please refer to the [Webpack 5 migration guide](https://webpack.js.org/migrate/5/) for more information.
