---
title: CMS element data resolving mismatch
issue: NEXT-22730
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed method `registerCmsElement` and `getCollectFunction` in `cms.service.js` to resolve entities to unique keys based on their index
* Changed method `enrich` in `elements/image-slider/index.js` to resolve entities to unique keys based on their index
* Changed method `enrich` in `elements/image-gallery/index.js` to resolve entities to unique keys based on their index
