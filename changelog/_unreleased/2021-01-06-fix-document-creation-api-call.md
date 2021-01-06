---
title: Fix document creation api call
author:             Daniel Huth
author_email:       daniel.huth@pickware.de
author_github:      @agreon
---
# Administration
* Fixed request handling in `Resources/app/administration/src/core/service/api/document.api.service.js` so that the
  resulting promise can be waited for in `Resources/app/administration/src/module/sw-order/component/sw-order-document-card/index.js`