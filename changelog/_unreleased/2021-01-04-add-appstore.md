---
title: Add Appstore
issue: NEXT-12608
flag: FEATURE_NEXT_12608
---

# Core

* Added following new classes:
    * `Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController`
    * `Shopware\Core\Framework\Store\Api\ExtensionStoreCategoryController`
    * `Shopware\Core\Framework\Store\Api\ExtensionStoreDataController`
    * `Shopware\Core\Framework\Store\Api\ExtensionStoreLicensesController`
    * `Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider`
    * `Shopware\Core\Framework\Store\Authentication\AuthenticationProvider`
    * `Shopware\Core\Framework\Store\Exception\ExtensionInstallException`
    * `Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException`
    * `Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException`
    * `Shopware\Core\Framework\Store\Exception\InvalidExtensionIdException`
    * `Shopware\Core\Framework\Store\Exception\InvalidExtensionRatingValueException`
    * `Shopware\Core\Framework\Store\Exception\InvalidVariantIdException`
    * `Shopware\Core\Framework\Store\Exception\LicenseNotFoundException`
    * `Shopware\Core\Framework\Store\Exception\VariantTypesNotAllowedException`
    * `Shopware\Core\Framework\Store\Helper\PermissionCategorization`
    * `Shopware\Core\Framework\Store\Search\EqualsFilterStruct`
    * `Shopware\Core\Framework\Store\Search\ExtensionCriteria`
    * `Shopware\Core\Framework\Store\Search\FilterStruct`
    * `Shopware\Core\Framework\Store\Search\MultiFilterStruct`
    * `Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider`
    * `Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService`
    * `Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService`
    * `Shopware\Core\Framework\Store\Services\AbstractStoreCategoryProvider`
    * `Shopware\Core\Framework\Store\Services\ExtensionDataProvider`
    * `Shopware\Core\Framework\Store\Services\ExtensionDownloader`
    * `Shopware\Core\Framework\Store\Services\ExtensionLifecycleService`
    * `Shopware\Core\Framework\Store\Services\ExtensionLoader`
    * `Shopware\Core\Framework\Store\Services\ExtensionStoreLicensesService`
    * `Shopware\Core\Framework\Store\Services\LicenseLoader`
    * `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService`
    * `Shopware\Core\Framework\Store\Services\StoreCategoryProvider`
    * `Shopware\Core\Framework\Store\Struct\BinaryCollection`
    * `Shopware\Core\Framework\Store\Struct\BinaryStruct`
    * `Shopware\Core\Framework\Store\Struct\CartPositionCollection`
    * `Shopware\Core\Framework\Store\Struct\CartPositionStruct`
    * `Shopware\Core\Framework\Store\Struct\CartStruct`
    * `Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct`
    * `Shopware\Core\Framework\Store\Struct\ExtensionCollection`
    * `Shopware\Core\Framework\Store\Struct\ExtensionStruct`
    * `Shopware\Core\Framework\Store\Struct\FaqCollection`
    * `Shopware\Core\Framework\Store\Struct\FaqStruct`
    * `Shopware\Core\Framework\Store\Struct\ImageCollection`
    * `Shopware\Core\Framework\Store\Struct\ImageStruct`
    * `Shopware\Core\Framework\Store\Struct\LicenseCollection`
    * `Shopware\Core\Framework\Store\Struct\LicenseStruct`
    * `Shopware\Core\Framework\Store\Struct\PermissionCollection`
    * `Shopware\Core\Framework\Store\Struct\PermissionStruct`
    * `Shopware\Core\Framework\Store\Struct\ReviewCollection`
    * `Shopware\Core\Framework\Store\Struct\ReviewStruct`
    * `Shopware\Core\Framework\Store\Struct\ReviewSummaryStruct`
    * `Shopware\Core\Framework\Store\Struct\StoreCategoryCollection`
    * `Shopware\Core\Framework\Store\Struct\StoreCategoryStruct`
    * `Shopware\Core\Framework\Store\Struct\StoreCollection`
    * `Shopware\Core\Framework\Store\Struct\StoreStruct`
    * `Shopware\Core\Framework\Store\Struct\VariantCollection`
    * `Shopware\Core\Framework\Store\Struct\VariantStruct`
    * `Shopware\Core\Framework\Test\Store\Api\ExtensionStoreActionsControllerTest`
    * `Shopware\Core\Framework\Test\Store\Api\ExtensionStoreCategoryControllerTest`
    * `Shopware\Core\Framework\Test\Store\Api\ExtensionStoreDataControllerTest`
    * `Shopware\Core\Framework\Test\Store\Api\ExtensionStoreLicensesControllerTest`
    * `Shopware\Core\Framework\Test\Store\Authentication\AuthenticationProviderTest`
    * `Shopware\Core\Framework\Test\Store\Search\ExtensionCriteriaTest`
    * `Shopware\Core\Framework\Test\Store\Search\FilterStructClassTest`
    * `Shopware\Core\Framework\Test\Store\Service\ExtensionDataProviderTest`
    * `Shopware\Core\Framework\Test\Store\Service\ExtensionDownloaderTest`
    * `Shopware\Core\Framework\Test\Store\Service\ExtensionLifecycleServiceTest`
    * `Shopware\Core\Framework\Test\Store\Service\ExtensionLoaderTest`
    * `Shopware\Core\Framework\Test\Store\Service\ExtensionStoreLicensesServiceTest`
    * `Shopware\Core\Framework\Test\Store\Service\LicenseLoaderTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Service\StoreCategoryProviderTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\ExtensionStructTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\PermissionCollectionTest`
    * `Swag\SaasRufus\Test\Core\Framework\Extension\Struct\ReviewStructTest`
    * `AppStoreTestPlugin\AppStoreTestPlugin`
* Added new method `Shopware\Core\Framework\App\Lifecycle\AppLoader:deleteApp`
* Added new parameter `$type` to method `Shopware\Core\Framework\Plugin\PluginExtractor:extract`
* Changed return value from method `Shopware\Core\Framework\Plugin\PluginManagementService:extractPluginZip` from `void` to `string`
* Added new method `Shopware\Core\Framework\Plugin\PluginZipDetector:isApp`
* Added new method `Shopware\Core\Framework\Store\Api\StoreController:categoriesAction`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:getCategories`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:listExtensions`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:listListingFilters`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:extensionDetail`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:extensionDetailReviews`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:createCart`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:orderCart`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:cancelSubscription`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:createRating`
* Added new method `Shopware\Core\Framework\Store\Services\StoreClient:getLicenses`
* Added new method `Shopware\Core\Framework\Store\Services\StoreService:getLanguageByContext`
* Added new method `Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct:getType`
