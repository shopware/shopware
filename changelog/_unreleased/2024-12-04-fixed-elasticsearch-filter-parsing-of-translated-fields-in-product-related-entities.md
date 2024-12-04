---
title: Fixed Elasticsearch Filter parsing of translated fields in product-related entities
issue: NEXT-37804
author: Martin Bens
author_email: martin.bens@it-bens.de
author_github: @spigandromeda
---
# Core

* Backported the `getAssociatedDefinition` method of the `EntityDefinitionQueryHelper` from NEXT-34674
* Added `v6.6.6.0` feature variance to the `testFilterParsing` method of the `CriteriaParserTest`
* Changed `Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to resolve related definitions before parsing a value while parsing a filter
