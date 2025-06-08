---
title: Add typescript declaration bindings for the third party interface
issue: NEXT-16406
author: Stephan Pohl
author_email: s.pohl@shopware.com
author_github: klarstil
---
# Administration
* Added typescript declarations for the `Shopware` third party interface object
* Added `jsconfig.json` for better VSCode support
    * Added `paths` to resolve Webpack alias in VSCode
* Added `main` property in `package.json` to declare the entry point of the administration
* Added `types` property in `package.json`  to point to the declaration files  

