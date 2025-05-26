---
title: Add Event to modify SalesChannelContext
issue: https://github.com/shopware/shopware/issues/9851
author: Björn Herzke
author_email: bjoern.herzke@brandung.de
author_github: @wrongspot
---

# Core
* Added Event `Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelContextEvent` to `Shopware\Core\Content\Sitemap\ScheduledTask\SitemapMessageHandler`
* Added Event `Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelContextEvent` to `Shopware\Core\Content\Sitemap\CommandsSitemapGenerateCommand`
* Added parameter `EventDispatcherInterface` to `Shopware\Core\Content\Sitemap\ScheduledTask\SitemapMessageHandler` constructor.