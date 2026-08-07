---
title: Restrict product reviews and sales channel criteria over their whole depth
issue: #19070
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition::processCriteria` to apply the review visibility rules of the `productReviews` association on every criteria nesting level. Store API criteria that load product reviews through a nested association now return approved reviews plus the pending reviews of the logged-in customer, as the top-level association already did. Integrations that read reviews through a nested association can receive fewer reviews than before.
* Changed `Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository` to apply the restrictions of the sales channel definitions — the sales channel scope and the entity specific filters such as product availability — to every node of a criteria. Previously only the first 99 nodes were processed and a criteria with more nested associations silently kept the remaining nodes unrestricted. Integrations that send large criteria to the Store API can receive fewer entities in deeply nested associations than before.
