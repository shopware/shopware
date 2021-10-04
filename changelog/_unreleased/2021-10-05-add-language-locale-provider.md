---
title: Add LanguageLocaleProvider
issue: NEXT-10606
---
# Core
* Added `\Shopware\Core\System\Locale\LanguageLocaleProvider` to fetch locales for languageIds.
* Changed `\Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Content\Product\Cart\ProductFeatureBuilder` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportGenerator` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Framework\Adapter\Translation\Translator` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Framework\App\AppLocaleProvider` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\Framework\Store\Services\ExtensionLoader` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
* Changed `\Shopware\Core\System\Currency\CurrencyFormatter` to use new `LanguageLocaleProvider`, instead of fetching locales manually.
