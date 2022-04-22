---
title: Implement sorting by association count
issue: NEXT-21006
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting`
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder::parseSorting` to read value of key `type` in iteration of the `sort` param and instanciate `CountSorting` if `type` equals `count`
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder::addSortings` to handle instances of `CountSorting`
* Added `Shopware\Elasticsearch\Sort\CountSort` as extension of `FieldSort`
* Changed `Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser::parseSorting` to return instance of `CountSort` if instance of `CountSorting` is given
