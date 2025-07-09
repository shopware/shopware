---
title: Fix product search SQL scoring
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder::addQueries` to correctly calculate scores from score queries when searching for products.
* Deprecated `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException`. It will be removed. Use `Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::invalidSortingDirection` instead.
