---
title: Clean-up temp files after use of MediaService
issue: NEXT-11827
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Core
* Added `cleanupTempFiles` method to `FileFetcher` to be used in the `MediaService` to clean up temp files after persisting them to the database.
