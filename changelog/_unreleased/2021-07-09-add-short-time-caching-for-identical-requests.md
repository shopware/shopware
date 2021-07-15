---
title: Add short time caching for identical requests
issue: NEXT-16011
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Added axios interceptor in the `http.factory` which includes a new adapter 
  which caches identical requests in short time amounts
