---
title: Fix theme copies overwriting each other's theme_runtime_config row
issue: 18658
author_github: @Happyfield7
---
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeRuntimeConfigService::refreshRuntimeConfig()` to persist theme copies (duplicates with an inherited implementation) with a `NULL` `technical_name`. Previously a copy was stored under its parent's `technical_name`, so multiple copies of the same parent plus the parent itself collided on the `uidx.technical_name` unique index and continuously overwrote each other's `theme_runtime_config` row (via `REPLACE INTO`) on every storefront request, causing permanent write load and MySQL deadlocks.
* Added `Shopware\Storefront\Theme\ThemeRuntimeConfigStorage::getOwnTechnicalName()` returning a theme's own technical name (`NULL` for copies), as opposed to the parent-inheriting `getThemeTechnicalName()`.
* Changed `Shopware\Storefront\Theme\ThemeRuntimeConfigStorage::save()` to retry the write on transient database deadlocks.
