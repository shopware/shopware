---
title: Ignore JS script files if they do not match the new file path pattern
issue: NEXT-33857
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Added condition to ignore JS script files if they do not match the new file path pattern ([see](https://github.com/shopware/shopware/discussions/3310)).

Example for a Theme called MyOldTheme (theme.json):
```json
...
"script": [
  "@Storefront",
  "@AnotherTheme",
  "app/storefront/dist/storefront/js/my-old-theme.js", // This file will be ignored (structure before 6.6)
  "app/storefront/dist/storefront/js/my-old-theme/my-old-theme.js", // This file will be used (new structure)
],
...
```
Without this change, the storefront would display an error with a non-updated theme.json.
