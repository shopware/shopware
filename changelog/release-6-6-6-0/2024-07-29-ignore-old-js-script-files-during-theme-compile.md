---
title: Ignore old js script files during theme compile
issue: NEXT-37454
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Added a filter to ignore files with the old js structure (e.g. `app/storefront/dist/storefront/js/prefix-name.js`) when compiling themes.  
  Make sure you have the new structure for your js files. Read more about the **js plugin script path** [here](https://developer.shopware.com/docs/guides/plugins/plugins/storefront/add-custom-javascript.html#plugin-script-path).
