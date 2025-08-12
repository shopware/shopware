---
title: Improve property group loading performance
issue: #11748
---
# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler::process` to fetch groups and options separately instead of joining the group for every option, and to chunk the fetched ids instead of using pagination to improve performance.
* Changed `\Shopware\Core\Content\Property\PropertyGroupCollection::sortByConfig` to use multisort to improve performance.
