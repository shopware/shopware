---
title: Use env local for web installer
issue: NEXT-24091
---

# Core

* Added support for multiple mailers defined in Symfony framework configuration

___

# Upgrade Information

## Change of environment variables

* Renamed following environment variables to use more generic environment variable name used by cloud providers:
  * `SHOPWARE_ES_HOSTS` to `OPENSEARCH_URL`
  * `MAILER_URL` to `MAILER_DSN`

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
