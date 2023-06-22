---
title: use file extension and mime type for validating uploads
issue: NEXT-28781
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Administration
* Added new service file `file-validation.service.ts` in `src/app/service`
* Added function `fileValidationService` to `src/app/service/file-validation.service.ts`
* Added function `checkByType` to `src/app/service/file-validation.service.ts`
* Added function `checkByExtension` to `src/app/service/file-validation.service.ts`
