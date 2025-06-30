---
title: add support to explicit define files for css and js compilation
author: Falko Hilbert
author_email: fhilbert@viosys.com
author_github: @Falko Hilbert
---
# Storefront
* Added support for explicit define files for CSS and JS compilation. with this change it is possible to reference an explicit scss or js file of a bundle in the `theme.json` file relative to the `Resources` directory.* e.g.:
```json
{
  "style": [
    "@MyParentTheme/app/storefront/src/scss/overrides.scss"
  ],
  "script": [
    "@MyParentTheme/app/storefront/dist/storefront/js/storefront.js"
  ]
}
```
