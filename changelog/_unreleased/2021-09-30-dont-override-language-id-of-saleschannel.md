---
title: Don't override languageId of SalesChannel in SalesChannelContext
issue: NEXT-17276
flag: FEATURE_NEXT_17276
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory::create()` to not override the languageId of the SalesChannel-Entity in the constructed SalesChannelContext.
* Added `\Shopware\Core\System\SalesChannel\SalesChannelContext::getLanguageId()`, which returns the LanguageId of the underlying Core-Context.
* Changed `\Shopware\Core\System\SalesChannel\SalesChannelContext::$context` to be protected instead of private, so the context is included after serialization.
___
# Upgrade Information

## LanguageId of SalesChannel in SalesChannelContext will not be overridden anymore
The languageId of the SalesChannel inside the SalesChannelContext will not be overridden by the current Language of the context anymore.
So if you need the current language from the context use `$salesChannelContext->getLanguageId()` instead of relying on the languageId of the SalesChannel.

### Before
```php
$currentLanguageId = $salesChannelContext->getSalesChannel()->getLanguageId();
```

### After
```php
$currentLanguageId = $salesChannelContext->getLanguageId();
```

### Store-Api
When calling the `/store-api/context` route, you now get the core context information in the response.
Instead of using `response.salesChannel.languageId`, please use `response.context.languageIdChain[0]` now.
