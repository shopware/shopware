---
title: Elasticsearch custom field mapping using config
issue: NEXT-21234
---

# Core

* Added new config `elasticsearch.product.custom_fields_mapping` to configure the mapping type of custom fields

___
# Upgrade Information

## Only configured custom fields will be indexed in Elasticsearch

With Shopware 6.5 only configured customFields in the YAML file will be indexed, to reduce issues with type errors.
The config can be created in the `config/packages/elasticsearch.yml` with the following config

```yaml
elasticsearch:
  product:
    custom_fields_mapping:
      some_date_field: datetime
```

See [\Shopware\Core\System\CustomField\CustomFieldTypes](https://github.com/shopware/platform/blob/0ca57ddee85e9ab00d1a15a44ddc8ff16c3bc37b/src/Core/System/CustomField/CustomFieldTypes.php#L7-L19) for the complete list of possible options
