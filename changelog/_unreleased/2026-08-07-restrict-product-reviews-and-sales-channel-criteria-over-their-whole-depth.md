---
title: Restrict nested product reviews and oversized sales channel criteria
issue: #19070
---
# Core
* Store API criteria that load product reviews through a nested association now apply the same review visibility rules as the top-level `productReviews` association: approved reviews, plus the pending reviews of the logged-in customer. Previously those rules were applied to the top-level association only. Integrations that read reviews through a nested association can receive fewer reviews than before.
* `SalesChannelRepository` applies the restrictions of the sales channel definitions — the sales channel scope and entity-specific filters such as product availability — to the first 99 criteria nodes it walks. Criteria with more nested associations than that kept the remaining nodes unrestricted. Such criteria are now rejected with a `400` and the error code `SYSTEM__CRITERIA_TOO_MANY_NESTED_CRITERIA` instead of being answered with partially restricted data. No storefront request produces criteria of that size; integrations that build them must split them into several requests.
