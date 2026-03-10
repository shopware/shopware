---
title: compatibility with OpenSearch 3.x
issue: shopware/shopware#12979
---
# Core
* Changed `\Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder::customFields` to omit custom fields's `properties` when its empty to comply with OpenSearch 3.x
___
# Upgrade Information

## Opensearch 3.x compatibility

OpenSearch 3.x introduced a breaking change that disallows defining index mapping fields with empty array `properties`. For e.g: 

```json
{
  "mappings": {
    "properties": {
      "customFields": {
        "type": "object",
        "properties": []
      }
    }
  }
}
```

So instead of defining a index mapping with empty `properties`, we should omit `properties` entirely or define it with empty object `{}`:

```json
{
  "mappings": {
    "properties": {
      "customFields": {
        "type": "object",
        "properties": {} // or can be omitted entirely
      }
    }
  }
}
```