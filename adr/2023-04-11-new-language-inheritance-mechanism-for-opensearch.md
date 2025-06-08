---
title: New language inheritance mechanism for opensearch
date: 2023-04-11
area: Core,Elasticsearch
tags: [Elasticsearch,Opensearch,Multilingual search]
---

## Context

Currently, when using Elasticsearch for searching on storefront, we are creating multiple indexes of each language. This would be fine till now however there are a few problems with it:

- We need to manage multiple indexes, if the shop's using multilingual, we need to create several indexes for each language, this is a big problem on cloud especially
- "Indices and shards are therefore not free from a cluster perspective, as there is some level of resource overhead for each index and shard."
- Everytime a record is updated, we need to update that record in every language indexes
- There's currently no fallback mechanism when searching, therefor duplicating default language data for each index is needed, but not every field is translatable, this take more storage for each index 

## Decision

### New feature flag

We introduce a new feature flag `ES_MULTILINGUAL_INDEX` to allow people to opt in to the new multilingual ES index immediately.

### New Elasticsearch data mapping structure

We changed the approach to Multilingual fields strategy following these criteria

1. Each searchable entity now have only one index for all languages (e.g sw_product)
2. Each translated field will be mapped as an `object field`, each language_id will be a key in the object
3. When searching for these fields, use multi-match search with <translated_field>.<context_lang_id>, <translated_field>.<parent_current_lang_id> and <translated_field>.<default_lang_id> as fallback, this way we have a fallback mechanism without needing duplicate data
4. Same logic applied when sorting with the help of a painless script (see 3.Sorting below)
5. When a new language is added or a record is update, we do a partial update instead of replacing the whole document, this will reduce the request update payload and thus improve indexing performance overall

Example:

### 1. Create mapping setting

**OLD structure**

```json
// PUT /sw_product
{
    "mappings": {
        "properties": {
            "productNumber": {
                "type": "keyword"
            },
            "name": {
                "type": "keyword",
                "fields": {
                    "text": {
                        "type": "text"
                    },
                    "ngram": {
                        "type": "text",
                        "analyzer": "sw_ngram_analyzer"
                    }
                }
            }
        }
    }
}
```

**NEW structure**

```json
// PUT /sw_product/_mapping
{
    "mappings": {
        "properties": {
            "productNumber": {
                "type": "keyword"
            },
            "name": {
                "properties": {
                    "en": {
                        "type": "keyword",
                        "fields": {
                            "text": {
                                "type": "text",
                                "analyzer": "sw_english_analyzer"
                            },
                            "ngram": {
                                "type": "text",
                                "analyzer": "sw_ngram_analyzer"
                            }
                        }
                    },
                    "de": {
                        "type": "keyword",
                        "fields": {
                            "text": {
                                "type": "text",
                                "analyzer": "sw_german_analyzer"
                            },
                            "ngram": {
                                "type": "text",
                                "analyzer": "sw_ngram_analyzer"
                            }
                        }
                    }
                }
            }
        }
    }
}
```

### 2. Searching

Assume we're searching products in german

```json
// GET /sw_product/_search
{
  "query": {
    "multi_match": {
      "query": "some keyword",
      "fields": [
          "title.de.search", // context languge
          "title.en.search" // fallback language
      ],
      "type": "best_fields" 
    }
  }
}
```

### 3. Sorting 

We add new painless scripts in `Framework/Indexing/Scripts/translated_field_sorting.groovy` and `Framework/Indexing/Scripts/numeric_translated_field_sorting.groovy`, this script then will be used when sorting

**Example: Sort products by name in DESC**

```json
// GET /sw_product/_search
{
    "query": {
      ...
    },
    "sort": [
        {
            "_script": {
                "type": "string",
                "script": {
                    "id": "translated_field_sorting",
                    "params": {
                        "field": "name",
                        "languages": [
                            "119317f1d1d1417c9e6fb0059c31a448", // context language
                            "2fbb5fe2e29a4d70aa5854ce7ce3e20b" // fallback language
                        ]
                    }
                },
                "order": "DESC"
            }
        }
    ]
}
```

## Adding a new language

- When a new language is created, we perform this request to update mapping includes new added language

```json
// PUT /sw_product/_mapping
{
    "properties": {
        "name": {
            "properties": {
                "<new_language_id>": {
                    "type": "keyword",
                    "fields": {
                        "text": {
                            "type": "text",
                            "analyzer": "<new_language_stop_words_analyzer>"
                        },
                        "ngram": {
                            "type": "text",
                            "analyzer": "sw_ngram_analyzer"
                        }
                    }
                }
            }
        }
    }
}
```

## Consequences

- From the next major version, old language based indexes will not be used any longer thus could be removed on es cluster
- When the feature is activated, the shop must reindex using command `bin/console es:index` in the next update
