---
title: Restrict nested product reviews and report an oversized sales channel criteria
issue: #19070
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition::processCriteria` to apply the review visibility rules of the `productReviews` association on every criteria nesting level. Store API criteria that load product reviews through a nested association now return approved reviews plus the pending reviews of the logged-in customer, as the top-level association already did. Integrations that read reviews through a nested association can receive fewer reviews than before.
* Changed `Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository` to reject a criteria that does not fit its walk instead of answering it with partially restricted data. The walk restricts the first 99 criteria nodes; a criteria with more nested associations now fails with a `400` and the error code `SYSTEM__CRITERIA_TOO_MANY_NESTED_CRITERIA`. No storefront request builds a criteria of that size; integrations that do must split it into several requests.
