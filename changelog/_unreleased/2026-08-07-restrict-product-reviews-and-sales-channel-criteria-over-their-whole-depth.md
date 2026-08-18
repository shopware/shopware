---
title: Restrict nested product reviews and oversized sales channel criteria
issue: #19070
---
# Core
* Changed Store API criteria that load product reviews through a nested association to apply the same review visibility rules as the top-level `productReviews` association: approved reviews, plus the pending reviews of the logged-in customer. Previously those rules were applied to the top-level association only. Integrations that read reviews through a nested association can receive fewer reviews than before.
* Changed `SalesChannelRepository` to reject criteria with more than 99 nested associations with a `400` and the error code `SYSTEM__CRITERIA_TOO_MANY_NESTED_CRITERIA`. It previously applied the sales channel scope and entity-specific filters only to the first 99 nodes. No storefront request produces criteria of that size; integrations that build them must split them into several requests.
