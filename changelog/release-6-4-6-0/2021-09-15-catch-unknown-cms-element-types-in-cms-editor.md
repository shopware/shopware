---
title: Add try-catch on loading cmsElements when cms element type is not registered
author: Joshua Behrens
issue: NEXT-17331
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Added additional try-catch in `src/Administration/Resources/app/administration/src/module/sw-cms/service/cmsDataResolver.service.js` to match the right catch in `src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Added additional check and warning in `src/Administration/Resources/app/administration/src/module/sw-cms/service/cmsDataResolver.service.js` to expect cms elements registry might not have a slot type registered
