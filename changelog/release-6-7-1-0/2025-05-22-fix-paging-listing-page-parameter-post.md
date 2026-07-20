---
title: Fix paging listing page parameter usage in post requests
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor` to correctly use the query parameter `p` as fallback, if there is no `p` parameter in the request (body)
