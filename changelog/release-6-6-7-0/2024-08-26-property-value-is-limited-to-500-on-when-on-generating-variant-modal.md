---
title: Property value is limited to 500 on when on generating variant modal
issue: NEXT-37654
---
# Administration
* Changed the computed property `propertyGroupOptionRepository` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js` to fix amounts of value of propery maximum
* Added an identification group filter, a term query based on searching context and a sort of name increase for the computed property `propertyGroupOptionCriteria` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js`
* Changed the method `onSearchOptions` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js` to avoid fetching data with no change of a search term
