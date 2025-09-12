---
title: Fully restore Symfony ResetInterface lifecycle in Kernel::handle
author: Mateusz Flasiński
author_email: mateuszflasinski@gmail.com
author_github: mateuszfl
---

# Core
* Changed: `Shopware\Core\Kernel::handle` now calls `parent::handle()` instead of a custom override, ensuring Symfony's internal request/reset lifecycle is preserved.
* Added: new method `preInitializePlugins()` to safely prepare Shopware plugins before boot without interfering with Symfony's reset mechanism.

___

# Upgrade Information
## Correct ResetInterface handling in long-running runtimes
When running Shopware in long-running environments (FrankenPHP, RoadRunner, Swoole), services implementing Symfony's `ResetInterface` are now reliably reset between requests.  
No configuration changes are required.
