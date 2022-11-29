---
title: Add range aggregation
issue: NEXT-23243
author: Léo Cunéaz
author_email: leo@e-frogg.com
author_github: inem0o
---
# Core
* Add `RangeAggregation` and `RangeResult`
* Add range aggregation parsing and hydration in `EntityAggregator`
* Add range aggregation handling in AggregationParser
* Add test on the aggregation range using database
* Add test on the range aggregation in the AggregationParser
* Add range aggregation parsing in `CriteriaParser`
* Add range aggregation hydration in `ElasticsearchEntityAggregatorHydrator`
* Add `ElasticsearchRangeAggregation` to manage the conversion of DAL aggregation to elasticsearch
* Add unit test on the aggregation range using elasticsearch
