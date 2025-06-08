---
title: Fix Admin web workers
issue: NEXT-33031
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `worker.init.ts` to report worker errors in each tab
* Added `src/core/worker/admin-worker`, containing the worker logic
* Added `src/core/worker/admin-worker.worker` to handle mobile workers
