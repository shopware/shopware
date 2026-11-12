---
title: Keep grouping for limit 1 when a total count is requested
issue: 20974
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder` to drop the grouping for a limit of 1 only when no total is requested, as in 6.7. Searches with a limit of 1 and a total count mode now count entities instead of joined rows when filtering over a to-many association or by a search term.
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher` so that searches using `Criteria::TOTAL_COUNT_MODE_NEXT_PAGES` without a limit return all matching entities after the requested offset and report the exact total, as `TOTAL_COUNT_MODE_EXACT` does. Previously, these searches returned at most one entity.
