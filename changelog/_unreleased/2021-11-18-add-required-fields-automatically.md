---
title: Add required fields automatically
issue: NEXT-17454
author: Nils Haberkamp
---
# Core
* Changed return type of `Shopware\Core\Content\ImportExport\Service\AbstractMappingService::getMappingFromTemplate` to `array` 
___
# Administration
* Added method `mergeMappings` to `src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page/index.js`
* Added method `countAutomatedValues` to `src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page/index.js`
* Deprecated parameter `mapping` of method `updateSorting` in `src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping/index.js`