---
title: Documents Module not reactive
issue: NEXT-10034
author: Niklas Limberg
author_github: @NiklasLimberg
---
# Administration
* Added `mapPropertyErrors` and `documentBaseConfig` in `module/sw-settings-document/page/sw-settings-document-detail/index.js` to map API Errors
* Added `documentBaseConfigNameError` and `documentBaseConfigDocumentTypeIdError` to `module/sw-settings-document/page/sw-settings-document-detail/sw-settings-document-detail.html.twig`
* Changed `loadEntityData` to make `this.documentConfig` optional in `module/sw-settings-document/page/sw-settings-document-detail/index.js`
* Added `this.$router.replace` in `module/sw-settings-document/page/sw-settings-document-detail/index.js` to to redirect to edit route after a newly created document is saved
