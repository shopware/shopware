---
title: Remove vendor chunk and optimize code splitting
issue: NEXT-21612
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed `sw-chart` and `sw-code-editor` to async components
* Changed the initial startup file to `src/index.ts` which combines `src/core/shopware` and `src/app/main`
* Removed 'runtime-vendor' chunk to optimize code splitting
* Removed entrypoint `commons` which gets now loaded via the `src/index.ts`
