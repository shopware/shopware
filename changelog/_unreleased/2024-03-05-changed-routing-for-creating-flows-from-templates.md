---
title: Changed routing for creating flows from templates
issue: NEXT-34109
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Administration
* Added the optional parameter `flowTemplateId`, which should be a uuid of a existing flow template, to the `sw.flow.create` route, configured in `src/Administration/Resources/app/administration/src/module/sw-flow/index.js`.
* Changed the routes for the `sw-tab-item` components in `src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-detail/index.js` so that a `flowTemplateId` parameter is always included when it is set in the current route configuration.
