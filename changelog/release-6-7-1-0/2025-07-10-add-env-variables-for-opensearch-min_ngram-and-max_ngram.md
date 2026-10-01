---
title: add env variables for Opensearch min_ngram and max_ngram
issue: 11131
---
# Core
* Changed `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml` to allow setting `min_ngram` and `max_ngram` for Opensearch via environment variables `SHOPWARE_ES_NGRAM_MIN_GRAM` (default as 4) and `SHOPWARE_ES_NGRAM_MAX_GRAM` (default as 5).