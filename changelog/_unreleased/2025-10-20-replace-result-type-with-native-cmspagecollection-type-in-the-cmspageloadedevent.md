---
title: Replace `$result` type with native `CmsPageCollection` type in the `CmsPageLoadedEvent`
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the native type of the `$result` parameter in the `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent` from `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection` to `Shopware\Core\Content\Cms\CmsPageCollection`
* Changed the native return type of the method `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent::getResult` from `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection` to `Shopware\Core\Content\Cms\CmsPageCollection`
___
# Next Major Version Changes
## `CmsPageLoadedEvent::$result` now requires `CmsPageCollection` type

The `$result` property of `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent` now enforces the `Shopware\Core\Content\Cms\CmsPageCollection` type instead of the generic `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`.

The event constructor now requires `CmsPageCollection` explicitly, and `CmsPageLoadedEvent::getResult()` return type has changed from `EntityCollection` to `CmsPageCollection`.
