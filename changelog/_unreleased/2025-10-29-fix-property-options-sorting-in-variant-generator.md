---
title:              Fix property options sorting in variant generator
author:             Yvo Keller
author_email:       hi@yvo.ai
author_github:      yvokeller  
---
# Administration
* Changed method `loadOptions()` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js` to properly apply the configured `sortingType` of property groups by calling `sortOptions()` on the loaded options.
* Changed computed property `propertyGroupOptionCriteria()` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js` to always add the `group` association, which is required for proper sorting.
