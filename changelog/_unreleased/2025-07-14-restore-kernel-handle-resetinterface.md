---
title:              Restore ResetInterface support in long-running runtimes
issue:              #11215
author:             Mateusz Flasiński
author_email:       mateuszflasinski@gmail.com
author_github:      @mateuszfl
---

# Core

* Changed `Shopware\Core\Kernel` to restore support for Symfony's `ResetInterface` by adjusting the `boot()` method and preserving a minimal `handle()` override.
* Changed behavior in long-running runtimes (e.g., FrankenPHP, RoadRunner) to ensure that `services_resetter` is called between requests.
* Removed redundant logic in `Kernel::boot()` that prevented Symfony's reset lifecycle from executing correctly.

___

# Upgrade Information

If you are running Shopware in a long-running environment (e.g., FrankenPHP or RoadRunner), this change enables Symfony to properly reset services implementing `ResetInterface` between requests. No configuration changes are required.
