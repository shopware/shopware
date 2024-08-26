---
title: Add chunkhash for async JS built files
issue: NEXT-37279
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Changed `webpack.config.js` to add a chunkhash for async JS built files (chunks) and clean up.
  * This allows you to better cache the JS files in the browser.
  * If you want to use this feature you at least have to run `composer run build:storefront` once after the update.
  * This change will also clean up the `dist` folder in plugins/apps and core. So make sure all files below 
    `/dist/storefront` can be rebuild.
* Added webpack Plugin `FilenameToChunkNamePlugin.js` to shorten the filename and remove the path in production.
___
# Upgrade Information

## Shortened filenames with hashes for async JS built files
When building the Storefront JS-files for production using `composer run build:storefront`, the async bundle filenames no longer contain the filepath.
Instead, only the filename is used with a chunkhash / dynamic version number. This also helps to identify which files have changed after build. Similar to the main entry file like e.g. `cms-extensions.js?1720776107`.

**JS Filename before change in dist:**
```
└── custom/apps/
    └── ExampleCmsExtensions/src/Resources/app/storefront/dist/storefront/js/
        └── cms-extensions/           
            ├── cms-extensions.js <-- The main entry pint JS-bundle
            └── custom_plugins_CmsExtensions_src_Resources_app_storefront_src_cms-extensions-quickview.js  <-- Complete path in filename
```

**JS Filename after change in dist:**
```
└── custom/apps/
    └── ExampleCmsExtensions/src/Resources/app/storefront/dist/storefront/js/
        └── cms-extensions/           
            ├── cms-extensions.js <-- The main entry pint JS-bundle
            └── cms-extensions-quickview.plugin.423fc1.js <-- Filename and chunkhash
```
