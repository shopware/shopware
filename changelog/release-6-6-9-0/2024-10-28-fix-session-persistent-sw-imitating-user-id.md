---
title: Fix session persistent sw-imitating-user-id
issue: NEXT-39277
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Framework/Routing/SalesChannelRequestContextResolver` to clear the `sw-imitating-user-id` from the session & context, if there is no customer
