<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Content\Category\CategoryHydrator;
use Shopware\Core\Content\Media\MediaHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordHydrator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityHydrator;
use Shopware\Core\Content\Product\ProductHydrator;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingHydrator;
use Shopware\Core\Content\ProductExport\ProductExportHydrator;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterHydrator;
use Shopware\Core\Content\ProductStream\ProductStreamHydrator;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionHydrator;
use Shopware\Core\Content\Property\PropertyGroupHydrator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CategoryHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductConfiguratorSettingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductPriceHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchKeywordHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductKeywordDictionaryHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductReviewHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductManufacturerHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductMediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductCrossSellingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductCrossSellingAssignedProductsHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductFeatureSetHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSortingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchConfigHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchConfigFieldHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductVisibilityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductStreamHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductStreamFilterHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductExportHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(PropertyGroupHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(PropertyGroupOptionHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(MediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);
};
