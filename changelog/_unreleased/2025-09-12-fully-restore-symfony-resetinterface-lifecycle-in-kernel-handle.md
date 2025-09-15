---
title: Fully restore Symfony ResetInterface lifecycle in Kernel::handle
author: Mateusz Flasiński
author_email: mateuszflasinski@gmail.com
author_github: mateuszfl
---

# Core
* Changed: `Shopware\Core\Kernel::handle` now tracks nested request handling and defers service resets; once the outermost request finishes, `boot()` triggers `services_resetter` to restore all `ResetInterface` services. This aligns the per-request reset lifecycle with Symfony in long‑running runtimes.
* Added: internal properties `requestStackSize` and `resetServices`, a `__clone()` override to clear them, and clearing these flags again in `shutdown()` to avoid stale state across reboots.

___

# Upgrade Information
## Correct ResetInterface handling in long-running runtimes
When running Shopware in long-running environments (FrankenPHP, RoadRunner, Swoole), services implementing Symfony's `ResetInterface` are now reliably reset after each completed request cycle. The kernel defers resets while nested requests are in progress and performs a single reset once the outermost request completes.  
No configuration or code changes are required.

