---
title: Fix cache clear in cluster setups
issue: NEXT-30352
---
# Core
* Added new `shopware.deployment.cluster_setup` config option
* Changed `\Shopware\Core\Framework\Adapter\Cache\CacheClearer` to use `shopware.deployment.cluster_setup` config option and not clear filesystem caches on cluster deployments
___ 
# Upgrade Information
## Cluster setup configuration

There is a new configuration option `shopware.deployment.cluster_setup` which is set to `false` by default. If you are using a cluster setup, you need to set this option to `true` in your `config/packages/shopware.yaml` file.