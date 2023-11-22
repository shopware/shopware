---
title: Improved installing an extension will activate it
issue: NEXT-23563
author: p.dinkhoff
---
# Administration
* Changed the behaviour of installing an extension. Now the extension will be activated after installing it.
* Added a function `installAndActivateExtension`, to `component/sw-self-maintained-extension-card/index.js` and `component/sw-extension-card-bought/index.js`
* Changed the function `installExtension` to `installAndActivateExtension` in `component/sw-extension-card-base/index.js`
