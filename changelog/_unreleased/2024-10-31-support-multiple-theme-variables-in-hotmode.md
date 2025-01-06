---
title: Support multiple theme variables in HotMode
issue: NEXT-39374
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `BundleConfigGenerator` to know if an App or Bundle/Plugin is a Theme
* Changed `ThemeCompiler` to write theme-variables for every theme into a separate file
* Changed `ThemeDumpCommand` to use `themeId` and `technicalName` later in webpack config
* Changed `webpack.config.js` to use the theme dedicated variable file for correct CSS generation
