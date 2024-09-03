---
title: Add criteria nesting level
issue: NEXT-38080
---

# Core

* Added `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getNestingLevel` method to get the nesting level of the criteria.
* Changed `\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition::processCriteria` to add association only on root level.
