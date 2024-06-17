---
title: Improve composer executions while plugin lifecycle
issue: NEXT-36780
---

# Core

* Changed `\Shopware\Core\Framework\Plugin\Composer\CommandExecutor` to update only directly affected packages instead of all packages.
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService` to not modify vendor directory if Shopware is in cluster mode.
