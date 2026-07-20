---
title: Reduce event bus usage for AddCacheTagEvent
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `addTag` method to `Shopware\Core\Framework\Adapter\Cache\CacheTagCollector`
* Changed `Shopware\Core\Framework\Adapter\Translation\Translator` to use `addTag` method from `CacheTagCollector`
* Changed `Shopware\Core\System\SystemConfig\SystemConfigService` to use `addTag` method from `CacheTagCollector`
___
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeConfigValueAccessor` to use `addTag` method from `CacheTagCollector`
