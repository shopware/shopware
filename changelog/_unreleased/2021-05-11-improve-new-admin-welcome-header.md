---
title: improve new admin welcome header
issue: NEXT-15260
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: djpogo
---
# Administration
* Change personalized headlines on dashboard index. Only greet users personally when a `firstName` is set, any else the headline is not personalized. @see `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index/index.js`
