---
title: Replace `$result` type with native `CmsPageCollection` type in the `CmsPageLoadedEvent`
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated the `$result` parameter type in `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent` constructor. Will change from `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection` to `Shopware\Core\Content\Cms\CmsPageCollection` in v6.8.0
* Deprecated the return type of `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent::getResult()`. Will change from `EntityCollection` to `CmsPageCollection` in v6.8.0
___
# Next Major Version Changes
## `CmsPageLoadedEvent::$result` now requires `CmsPageCollection` type

The `$result` property of `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent` now enforces the `Shopware\Core\Content\Cms\CmsPageCollection` type instead of the generic `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`.

The event constructor now requires `CmsPageCollection` explicitly, and `CmsPageLoadedEvent::getResult()` return type has changed from `EntityCollection` to `CmsPageCollection`.
