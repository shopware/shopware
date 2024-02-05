---
title: Fix address modal closing
issue: NEXT-32095
---
# Storefront
* Changed `PluginManager.initializePlugins()` to be async. It now returns a `Promise` instead of `void`.
