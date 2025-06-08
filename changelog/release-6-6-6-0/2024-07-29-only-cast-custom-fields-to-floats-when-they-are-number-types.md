---
title: Only cast custom fields to floats when they are number types
issue: NEXT-37443
---
# Core
* Changed `src/Elasticsearch/Framework/ElasticsearchFieldMapper.php::formatCustomField()` to only cast numbers to floats when the type is a number and the value is valid number
