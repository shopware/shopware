---
title: Exclude custom fields of type `text` from possible float casting
issue: NEXT-33271
---
# Elasticsearch
* Changed `src/Elasticsearch/Framework/ElasticsearchFieldMapper.php::formatCustomField()` to not format custom fields of type `text`