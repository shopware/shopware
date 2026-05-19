---
title: Correctly delete bearerAuth cookie from base path in administration
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Administration
* Changed `Resources/app/administration/src/core/service/login.service.ts` to correctly delete the `bearerAuth` cookie also from the `context.basePath` if the logout function is called
