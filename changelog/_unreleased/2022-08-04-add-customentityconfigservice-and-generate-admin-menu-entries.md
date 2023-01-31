---
title: Add CustomEntityDefinitionService and generate admin menu entries
issue: NEXT-19272
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added `custom-entity-definition.service.ts` to save custom entity configs and to generate admin menu entries for custom entities with the `admin-ui` flag
* Added `sw-custom-entity/index.js` to register shared routes for `sw-generic-custom-entity-list` and `sw-generic-custom-entity-detail`
* Changed `init/repository.init.js` to add custom entity definitions to the `customEntityDefinitionService`
* Changed `sw-admin-menu/index.js` to include menu entries generated from `customEntityDefinitionService`
* Changed `src/app/main.ts` to register the `customEntityDefinitionService`
