---
title: Consistent JavaScript filename in Storefront
issue: NEXT-40060
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `src/Storefront/Resources/app/storefront/build/webpack/FilenameToChunkNamePlugin.js` to have consistent JavaScript filenames in the storefront.
  * If we have duplicate filenames, we will append the chunk id (numeric value, length of 5) to the filename.
* Changed `src/Storefront/Resources/app/storefront/webpack.config.js` to set hot option to true during HOT-Reloading. Removed `HotModuleReplacementPlugin` because it is already loaded in that case, leads to a smaller webpack config.
  * Also renamed the Hot-Reloading Entrypoint from `css` to `hot-reloading`.
___
# Upgrade Information

## Adjust duplicate async JS file names

We have made changes to have more consistent JavaScript filenames in the storefront. If we have duplicate filenames, we will append the chunk id (numeric value, length of 5) to the filename.

### Examples

Filenames **before** this change in different modes  
Hot-Reloading: `http://localhost:9999/storefront/plugin_scroll-up_scroll-up_plugin_js.js`  
Development: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/scroll-up.plugin.0ce767.js`  
Production: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/scroll-up.plugin.0ce767.js`  
Duplicate Filename: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/plugin_scroll-up_scroll-up_plugin_js.0ce767.js`

Filenames **after** this change in different modes  
Hot-Reloading: `http://localhost:9999/storefront/hot-reloading.scroll-up.plugin.js`  
Development: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/storefront.scroll-up.plugin.2e9f58.js`  
Production: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/storefront.scroll-up.plugin.2e9f58.js`  
Duplicate Filename: `http://localhost:8000/theme/fa1abe71af50c0c1fd964660ee680e66/js/storefront/storefront.scroll-up.plugin.45231.2e9f58.js`
