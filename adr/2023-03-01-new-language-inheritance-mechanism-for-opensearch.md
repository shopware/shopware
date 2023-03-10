---
title: New language inheritance mechanism for opensearch
date: 2023-03-07
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

We changed the approach to Multilingual fields strategy following these criteria

1. Each searchable entity have only one index (e.g sw_product)
2. Each translated field's mapping will have a language_id as suffix, other fields will be mapped normally (no suffix)
3. When searching for these fields, use multi-match search with <translated_field>_<context_lang_id>, <translated_field>_<parent_current_lang_id> and <translated_field>_<default_lang_id> as fallback, this way we have a fallback mechanism without needing duplicate data

Example:

1. Update setting

```json
PUT /sw_product
{
  "mappings": {
    "properties" : {
        "productNumber" : {
          "type" : "keyword"
        }, 
        "title_en" : {
          "type" : "text",
          "analyzer" : "english"
        },  
        "title_de" : {
          "type" : "text",
          "analyzer" : "deutsch"
        }
      }
    }
}
```

2. Searching

Assume we're searching products in german

```json
GET /sw_product/_search
{
  "query": {
    "multi_match": {
      "query": "some keyword",
      "fields": [
          "title_de^2", // as we want to prioritize german language for example
          "title_en" // as a fallback
      ],
      "type": "best_fields" 
    }
  }
}

```

## Adding a new language

- When a new language is used for a sales channel and translated fields of that language is not existed, we perform two requests:

1. a put mapping API to create new fields for 
2. a bulk update API to update partial document for those new created fields

## Consequences

- Old language based indexes will not be used any longer thus will be removed
- The shop must reindex (`bin/console es:index`) in the next update
