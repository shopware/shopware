---
title: Remove Api Version
issue: NEXT-10665
---

# Administration

* Deleted following file `src/Administration/Resources/app/administration/test/core/factory/http.factory.spec.js`
* Removed `context/setApiApiVersion` from `Shopware.State`
* Removed `getApiVersion` from API Service
* Changed Cypress tests to consider `apiPath` env variable
___
# Core
___
* Removed parameter `$version` from method `Shopware\Core\Checkout\Order\Api\OrderActionController:orderStateTransition`
* Removed parameter `$version` from method `Shopware\Core\Checkout\Order\Api\OrderActionController:orderTransactionStateTransition`
* Removed parameter `$version` from method `Shopware\Core\Checkout\Order\Api\OrderActionController:orderDeliveryStateTransition`
* Removed parameter `$version` from method `Shopware\Core\Content\ImportExport\Controller\ImportExportActionController:initiate`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\DefinitionService:generate`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\DefinitionService:getSchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:supports`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:generate`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator:getSchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder:getSchemaByDefinition`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder:enrich`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:supports`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:generate`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator:getSchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:supports`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:generate`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator:getSchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\ApiController:compositeSearch`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\ApiController:clone`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\ApiController:createVersion`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\ApiController:mergeVersion`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\InfoController:info`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\InfoController:openApiSchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\InfoController:entitySchema`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\InfoController:infoHtml`
* Removed parameter `$version` from method `Shopware\Core\Framework\Api\Controller\SyncController:sync`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiConverter:getApiVersion`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiConverter:isDeprecated`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiConverter:isFromFuture`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiConverter:getDeprecations`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiConverter:getNewFields`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:isAllowed`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:convertEntity`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:convertPayload`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:validateEntityPath`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:convertCriteria`
* Removed method `Shopware\Core\Framework\Api\Converter\ApiVersionConverter:ignoreDeprecations`
* Removed method `Shopware\Core\Framework\Api\Converter\ConverterRegistry:isDeprecated`
* Removed method `Shopware\Core\Framework\Api\Converter\ConverterRegistry:isFromFuture`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\ConverterRegistry:convert`
* Changed return value from method `Shopware\Core\Framework\Api\Converter\ConverterRegistry:getConverters` from `array` to `iterable`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\ConverterRegistry:getConverters`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\DefaultApiConverter:convert`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\DefaultApiConverter:isDeprecated`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Converter\DefaultApiConverter:getDeprecations`
* Removed method `Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase:getVersion`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Serializer\JsonApiEncoder:encode`
* Removed method `Shopware\Core\Framework\Api\Serializer\JsonApiEncodingResult:getApiVersion`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder:encode`
* Removed method `Shopware\Core\Framework\Api\Sync\SyncOperation:getApiVersion`
* Removed parameter `$apiVersion` from method `Shopware\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher:search`
* Removed parameter `$version` from method `Shopware\Core\Framework\Plugin\Api\PluginController:deletePlugin`
* Removed parameter `$version` from method `Shopware\Core\Framework\Plugin\Api\PluginController:installPlugin`
* Removed parameter `$version` from method `Shopware\Core\Framework\Plugin\Api\PluginController:uninstallPlugin`
* Removed parameter `$version` from method `Shopware\Core\Framework\Plugin\Api\PluginController:activatePlugin`
* Removed parameter `$version` from method `Shopware\Core\Framework\Plugin\Api\PluginController:deactivatePlugin`
* Removed method `Shopware\Core\Framework\Test\Api\Sync\SyncServiceTest:testWriteDeprecatedFieldLeadsToError`
* Removed method `Shopware\Core\Framework\Test\Api\Sync\SyncServiceTest:testWriteDeprecatedEntityLeadsToError`
* Removed parameter `$apiVersion` from method `Shopware\Core\System\SalesChannel\Api\StructEncoder:encode`
* Removed parameter `$version` from method `Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:info`
* Removed parameter `$version` from method `Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:openApiSchema`
* Removed parameter `$version` from method `Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController:infoHtml`
* Deleted following classes:
    * `Shopware\Core\Framework\Api\ApiVersion\ApiVersionSubscriber`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionNotAllowedException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureEntityException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\QueryFutureFieldException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedEntityException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\QueryRemovedFieldException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\WriteFutureFieldException`
    * `Shopware\Core\Framework\Api\Converter\Exceptions\WriteRemovedFieldException`
    * `Shopware\Core\Framework\Test\Api\ApiVersion\ApiVersionSubscriberTest`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV2Test`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV3Test`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\ApiVersioningV4Test`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV2`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV3`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\ApiConverter\ConverterV4`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleCollection`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleEntity`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleCollection`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2\BundleEntity`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundlePrice\BundlePriceDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundleTanslation\BundleTranslationDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleCollection`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleEntity`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\Aggregate\BundlePrice\BundlePriceDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\Aggregate\BundleTanslation\BundleTranslationDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleCollection`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleDefinition`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4\BundleEntity`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571753490v1`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571754409v2`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1571832058v3`
    * `Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations\Migration1572528079v4`
    * `Shopware\Core\Framework\Test\Api\Converter\ApiVersionConverterTest`
    * `Shopware\Core\Framework\Test\Api\Converter\DefaultApiConverterTest`
    * `Shopware\Core\Framework\Test\Api\Converter\fixtures\NewEntityDefinition`

