---
title: Add symplify phpstan rules
issue: NEXT-25940
---
# Core
* Added symplify/phpstan-rules to the dev dependencies and activated some new rules for the CI.
* Deprecated class `\Shopware\Core\Framework\Adapter\Filesystem\Filesystem` it will be removed in v6.6.0.0 as it was unused.
* Deprecated class `\Shopware\Core\Framework\Struct\Serializer\StructDecoder` it will be removed in v6.6.0.0 as it was unused.
* Deprecated properties `id`, `name` and `quantity` in `\Shopware\Core\Content\Product\Cart\PurchaseStepsError` and `\Shopware\Core\Content\Product\Cart\ProductStockReachedError`, the properties will become private and natively typed in v6.6.0.0.
* Deprecated properties `redis` and `connection` in `\Shopware\Core\Checkout\Cart\Command\CartMigrateCommand`, those will become private and readonly in v6.6.0.0.
___
# Upgrade Information
## Fix method signatures to comply with parent class/interface signature
The following method signatures were changed to comply with the parent class/interface signature:
**Visibility changes:**
* Method `configure()` was changed from public to protected in:
  * `Shopware\Storefront\Theme\Command\ThemeCompileCommand`
* Method `execute()` was changed from public to protected in:
  * `Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand`
  * `Shopware\Core\DevOps\System\Command\SystemDumpDatabaseCommand`
  * `Shopware\Core\DevOps\System\Command\SystemRestoreDatabaseCommand`
  * `Shopware\Core\DevOps\Docs\App\DocsAppEventCommand`
  * 
* Method `getExpectedClass()` was changed from public to protected in:
  * `Shopware\Storefront\Theme\ThemeSalesChannelCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginRecommendationCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginCategoryCollection`
  * `Shopware\Core\Framework\Store\Struct\LicenseDomainCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginRegionCollection`
  * `Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection`
  * `Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection`
  * `Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection`
  * `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection`
  * `Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection`
* Method `getParentDefinitionClass()` was changed from public to protected in:
  * `Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition`
  * `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition`
  * `Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition`
* Method `getDecorated()` was changed from public to protected in:
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryRoute`
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryStateRoute`
* Method `getSerializerClass()` was changed from public to protected in:
  * `Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField`

**Parameter type changes:**
* Changed parameter `$url` to `string` in:
  * `Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache#purge()`
* Changed parameter `$data` and `$format` to `string` in:
  * `Shopware\Core\Framework\Struct\Serializer\StructDecoder#decode()`
  * `Shopware\Core\Framework\Struct\Serializer\StructDecoder#supportsDecoding()`
  * `Shopware\Core\Framework\Api\Serializer\JsonApiDecoder#decode()`
  * `Shopware\Core\Framework\Api\Serializer\JsonApiDecoder#supportsDecoding()`
* Changed parameter `$storageName` and `$propertyName` to `string` in:
  * `Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields#__construct()`
* Changed parameter `$event` to `object` in:
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#dispatch()`
* Changed parameter `$listener` to `callable` in:
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#removeListener()`
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#getListenerPriority()`
  * `Shopware\Core\Framework\Webhook\WebhookDispatcher#removeListener()`
  * `Shopware\Core\Framework\Webhook\WebhookDispatcher#getListenerPriority()`
* Changed parameter `$constraints` to `Symfony\Component\Validator\Constraint|array|null` in:
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validate()`
* Changed parameter `$object` to `object`, `$propertyName` to `string`, `$groups` to `string|Symfony\Component\Validator\Constraints\GroupSequence|array|null` and `$objectOrClass` to `object|string` in:
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validateProperty()`
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validatePropertyValue()`
* Changed parameter `$record` to `iterable` in:
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe#in()`
* Changed parameter `$warmupDir` to `string` in:
  * `Shopware\Core\Kernel#reboot()`

