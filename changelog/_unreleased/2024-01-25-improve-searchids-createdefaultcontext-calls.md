---
title: Improve searchIds & createDefaultContext calls
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `AddressValidator` to directly fetch the `salesChannelCountryId` from the mapping table
* Changed the `AddressValidator` to use the `sales_channel_country` repository
* Changed the `CreatePageCommand` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `ImportEntityCommand` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `PrimaryKeyResolver` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `ProductCrossSellingSerializer` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `ProductSerializer` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `UnusedMediaPurger` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `NewsletterRecipientTaskHandler` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `ExportController` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `ScriptPersister` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `TaskRegistry` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `WebhookDispatcher` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `RetryWebhookMessageFailedSubscriber` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `SalesChannelCreator` to reduce/improve the amount of `Context::createDefaultContext()` calls
* Changed the `BannerService` to reduce/improve the amount of `Context::createDefaultContext()` calls
___
# Storefront
* Changed the `ThemeLifecycleService` to only fetch the required id not the entire MediaFolderEntity
* Changed the `ThemeLifecycleService` to only fetch the required id not the entire ParentThemeEntity
