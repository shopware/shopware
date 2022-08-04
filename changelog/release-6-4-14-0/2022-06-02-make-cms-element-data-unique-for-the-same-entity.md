---
title: Make cms element data unique for the same entity
issue: NEXT-21862
author: Michiel Kalle
author_email: m.kalle@xsarus.nl
author_github: michielkalle
---
# Administration
* Changed method `registerCmsElement` in `src/Administration/Resources/app/administration/src/module/sw-cms/service/cms.service.js` to make cms element properties unique regarding the same entity
* Added method `getCollectFunction` in `src/Administration/Resources/app/administration/src/module/sw-cms/service/cms.service.js` and used it in the following cms elements to make their properties unique regarding the same entity:
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/buy-box/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/cross-selling/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-box/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-description-reviews/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-slider/index.js`
* Changed method `enrich` to make cms element properties unique regarding the same entity in:
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-gallery/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-slider/index.js`
