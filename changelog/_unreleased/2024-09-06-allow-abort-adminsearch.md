---
title: Allow admin-search to get aborted by new request
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `elastic` function in `search.api.service` to abort the previous search on function call
* Changed `searchQuery` function in `search.api.service` to abort the previous search on function call
