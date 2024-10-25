---
title: Fix custom field content in flow builder
issue: NEXT-00000
author: Cedric Engler
author_email: cedric.engler@pickware.de
---

# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-flow/state/flow.state.js` to remove unnecessary deduplication filter of `avaialableActions` getter.
* Changed `src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/index.js` to add a preselected entity.
* Changed `src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-affiliate-and-campaign-code-modal/index.js` to add a preselected entity.
* Changed `src/Administration/Resources/app/administration/src/module/sw-flow/constant/flow.constant.js` to fix the action group mapping.
* Changed `src/Administration/Resources/app/administration/src/module/sw-flow/service/flow-builder.service.ts` to add a new method to get the entity name of an action.
