---
title:              Fix property options sorting in variant generator
author:             Yvo Keller
author_email:       hi@yvo.ai
author_github:      yvokeller
---
# Administration
* Changed computed property `propertyGroupOptionCriteria()` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js` to disable natural sorting (third parameter set to `false`) when sorting property group options by name, ensuring correct alphanumeric sorting.
