---
title: Prevent empty criteria ids
issue: NEXT-16710
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::__construct`, which throws an exception, in 6.5, if an empty ids array provided
