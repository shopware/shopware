---
title: Add contenthash for core js plugins
issue: NEXT-37279
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Changed `webpack.config.js` to add a contenthash for async JS built files (chunks).
  * This allows you to better cache the JS files in the browser.
  * If you want to use this feature you at least have to run `composer run build:storefront` once after the update.
  * This change will also clean up the `dist` folder in plugins/apps and core. So make sure all files below 
    `/dist/storefront` can be rebuild.
