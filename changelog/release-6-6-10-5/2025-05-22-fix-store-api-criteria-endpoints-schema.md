---
title: Fix StoreAPI criteria endpoints schema
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed multiple OpenAPI schema endpoints of the StoreAPI to use the `NoneFieldsCriteria` where the endpoint can't return a `PartialEntity`
