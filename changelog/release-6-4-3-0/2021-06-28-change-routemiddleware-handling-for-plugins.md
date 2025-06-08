---
title: Change routeMiddleware handling for plugins
issue: NEXT-15358
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---
# Administration
* Changed `go()` function in `src/Administration/Resources/app/administration/src/core/helper/middleware.helper.js` to handle `routeMiddleware()` calls different to circumstand scanrios where `routeMiddleware()` is not properly called.