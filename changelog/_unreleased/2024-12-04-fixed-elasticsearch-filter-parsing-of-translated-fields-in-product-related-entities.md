---
title: Fixed Elasticsearch Filter parsing of translated fields in product-related entities
issue: NEXT-37804
author: Martin Bens
author_email: martin.bens@it-bens.de
author_github: @spigandromeda
---
# Core
* Added the `getAssociatedDefinition` method of the `EntityDefinitionQueryHelper` to resolve the definition of the provided field. For e.g `product.unit.shortCode` will resolve the definition of the `unit` field in the `product` entity
* Changed `Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to resolve related definitions before parsing a value while parsing a filter
