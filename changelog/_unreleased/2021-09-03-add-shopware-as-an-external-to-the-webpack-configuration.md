---
title: Add Shopware as an external to the webpack configuration
issue: NEXT-16380
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Added `Shopware` to the `externals` in the webpack configuration. This allows to import Shopware (e.g. `import { Module } from 'Shopware'`) instead of using the global Shopware object (e.g. `const { Module } = Shopware`). When plugins are using this they need to add `Shopware` to the "paths" in their `jsconfig.json` which redirect do `src/core/shopware`. It could also lead to an ESLint failure of `import/order` because the import have to placed before the local imports. Then you need to move the import to the top.
