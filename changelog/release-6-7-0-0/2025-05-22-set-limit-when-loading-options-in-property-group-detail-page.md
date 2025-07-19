---
title: Set Limit when loading options in property group detail page
issue: 9715
---
# Administration
* Deprecated `dataSource` computed property in `src/module/sw-property/component/sw-property-option-list/index.js` due to unused 
* Changed the template `src/module/sw-property/component/sw-property-option-list/sw-property-option-list.html.twig` to not use `localMode` of `sw-one-to-many-grid` component
* Changed computed property `defaultCriteria` in `src/module/sw-property/page/sw-property-detail/index.js` to set a limit of the `options` association
* Changed the component `src/module/sw-property/component/sw-property-option-list/index.js` to check the empty state after loading the options
* Changed the template `src/module/sw-property/component/sw-property-option-list/sw-property-option-list.html.twig` to show the empty state when no options are available