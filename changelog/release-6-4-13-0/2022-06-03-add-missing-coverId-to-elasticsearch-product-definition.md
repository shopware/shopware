---
title: Add missing coverId to ElasticsearchProductDefinition
issue: NEXT-21867
author: Michiel Kalle
author_email: m.kalle@xsarus.nl
author_github: michielkalle
---
# Core
* Changed `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition::getMapping()` to also return the property `coverId` 
