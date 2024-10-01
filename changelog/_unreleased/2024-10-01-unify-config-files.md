---
title: Unify config files
issue: NEXT-38616
---
# Upgrade Information

## Search server now provides OpenSearch/Elasticsearch shards and replicas

Previously we had an default configuration of three shards and three replicas. With 6.7 we removed this default configuration and now the search server is responsible for providing the correct configuration.
This allows that the indices automatically scale based on your nodes available in the cluster.

You can revert to the old behavior by setting the following configuration in your `config/packages/shopware.yml`:

```yaml
elasticsearch:
    index_settings:
        number_of_shards: 3
        number_of_replicas: 3
```

