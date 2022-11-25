---
title: Use env local for web installer
issue: NEXT-24091
---

# Core

* Renamed `SHOPWARE_ES_HOSTS` to `OPENSEARCH_URL` to use more generic environment variable name used by cloud providers.

You can change this variable back in your installation using a `config/packages/elasticsearch.yaml` with

```yaml
elasticsearch:
    hosts: "%env(string:SHOPWARE_ES_HOSTS)%"
```

or prepare your env by replacing the var with the new one like

```yaml
elasticsearch:
    hosts: "%env(string:OPENSEARCH_URL)%"
```
