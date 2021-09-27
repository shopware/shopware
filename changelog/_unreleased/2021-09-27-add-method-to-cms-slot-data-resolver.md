---
title: Add possibility to add data resolvers without injecting new services
issue: NEXT-???
author: tyurderi (Net Inventors)
author_email: info@netinventors.de
---

# Core

* Added method `addResolver` to `\Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver`
* Added new constructor parameter `EventDispatcherInterface $eventDispatcher` to `\Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver`
* Added new event `\Shopware\Core\Content\Cms\Events\CmsSlotDataResolverEvent` that is being thrown in `\Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver::resolve`