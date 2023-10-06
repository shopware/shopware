---
title: Fixing undefined data in listing flow template
issue: NEXT-17997
---
# Administration
* Added `v-if` and `v-else` to `src/Administration/Resources/app/administration/src/module/sw-flow/view/listing/sw-flow-list-flow-templates/sw-flow-list-flow-templates.html.twig` to handle the render of table.
* Changed `routeDetailTab` method in `src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-detail/index.js` to return a route object for detail tab instead of name of route.
