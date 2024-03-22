---
title: Improve file validation service
issue: NEXT-32034
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed function `checkByExtension` in `src/app/service/file-validation.service.ts` to use last part of filename as extension.
* Added missing mime types to `extensionByType` map in `src/app/service/file-validation.service.ts`.
* Added `src/app/service/file-validation.service.ts` service provider.
