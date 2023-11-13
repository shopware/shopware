---
title: Implement dynamig Custom Entity Detail page
issue: NEXT-22643
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Administration
* Added `sw-generic-custom-entity-detail` page to handle `admin-ui` flagged Custom Entities
* Added `sw-custom-entity-input-field` to dynamically handle input of Custom Entities by type
* Added prop `entitySearchColor` to `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` to enable using an external color for the search type badge
* Changed `src/Administration/Resources/app/administration/src/global.types.ts` to handle module routes via TypeScript
* Changed `src/Administration/Resources/app/administration/src/module/index.js` to consider TypeScript module files as well
* Changed `src/Administration/Resources/app/administration/src/app/service/custom-entity-definition.service.ts` to consider module icons mentioned in the `entities.xml` as well
