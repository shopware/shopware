---
title: Fixed check the validation of import profiles
issue: NEXT-9430
---
# Administration
* Added `getDefaultProfileSelected` method to check the validation by the parent mapping of import profiles within `sw-import-export-edit-profile-modal`
* Added param `parentMapping` in method validate within `importExportProfileMapping.service.js` to compare with the validation of the parent mapping
