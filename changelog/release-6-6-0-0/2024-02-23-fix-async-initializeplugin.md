---
title: Fix async initialize of single JS-plugin
issue: NEXT-34001
---
# Storefront
* Changed `PluginManager.initializePlugin(pluginName, selector, options)` to be async and fetch the given plugin on-demand if it was never fetched beforehand.