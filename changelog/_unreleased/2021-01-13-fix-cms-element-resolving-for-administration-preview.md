---
title:              Fix cms element resolving for preview in administration
issue:              -
author:             Moritz Müller
author_email:       moritz@momocode.de
author_github:      @momocode-de
---
# Administration
* Changed method `registerCmsElement()` in `module/sw-cms/service/cms.service.js` to fix the cms element resolving for preview if there are multiple configurations having the same entity
