---
title: Fix import and export of list price
issue: NEXT-8419
author: Krispin Lütjann
author_email: k.luetjan@shopware.com 
author_github: King-of-Babylon
---
# Administration
* Added list price to the price properties of `Resources/app/administration/src/module/sw-import-export/component/sw-import-export-entity-path-select/index.js`
___
# Core
* Added serialization and deserialization of list price to the corresponding functions of `Core/Content/ImportExport/DataAbstractionLayer/Serializer/Field/PriceSerializer.php` 
