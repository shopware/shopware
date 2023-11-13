---
title: Add range aggregation
issue: NEXT-23243
author: Léo Cunéaz
author_email: leo@e-frogg.com
author_github: inem0o
---
# Core
* Added  `RangeAggregation` and `RangeResult`
* Added  range aggregation parsing and hydration in `EntityAggregator`
* Added  range aggregation handling in AggregationParser
* Added  test on the aggregation range using database
* Added  test on the range aggregation in the AggregationParser
* Added  range aggregation parsing in `CriteriaParser`
* Added  range aggregation hydration in `ElasticsearchEntityAggregatorHydrator`
* Added  `ElasticsearchRangeAggregation` to manage the conversion of DAL aggregation to elasticsearch
* Added  unit test on the aggregation range using elasticsearch
