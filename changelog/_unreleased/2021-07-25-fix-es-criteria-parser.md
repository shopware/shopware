---
title: Fix Elasticsearch CriteriaParser
issue: NEXT-16515
author: Pascal Josephy
author_email: pascal.josephy@jkweb.ch
author_github: pascaljosephy
---
# Core
*  Added method `src/Elasticsearch/Product/ElasticsearchProductDefinition:fetchPropertyGroups`
*  Changed method `src/Elasticsearch/Product/ElasticsearchProductDefinition:fetch`
*  Changed method `src/Elasticsearch/Product/ElasticsearchProductDefinition:getMapping`
*  Changed method `src/Elasticsearch/Framework/DataAbstractionLayer/CriteriaParser:parseAggregation` to not handle nesting for FilterAggregation
*  Changed method `src/Elasticsearch/Framework/DataAbstractionLayer/CriteriaParser:parseFilterAggregation` to handle nested queries and aggregations
