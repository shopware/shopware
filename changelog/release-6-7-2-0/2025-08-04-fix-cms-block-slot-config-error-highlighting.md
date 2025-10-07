---
title: Fix CMS block slot config error highlighting
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Deprecated `pageSlotconfigError` data property of `sw-cms-section` component.
* Changed `hasSlotConfigErrors` method of `sw-cms-section` component to use mapped `pageSlotConfigError` again.
* Changed return types of error mappers in `src/app/service/map-errors.service.ts`.
