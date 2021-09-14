---
title: Implement error boundaries
issue: NEXT-16473
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Added component `sw-error-boundary`
* Added error-boundary around the module router-view
* Added option `keepApiErrors` to repositoryFactory. Created repository with this option activated will not reset the error store.
