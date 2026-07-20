---
title: Fix override paging listing page parameter
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor` to no longer override the already present criteria offset / page, if there is no `p` request parameter
