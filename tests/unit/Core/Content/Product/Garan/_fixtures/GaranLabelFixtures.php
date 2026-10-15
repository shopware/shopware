<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan\_fixtures;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
final class GaranLabelFixtures
{
    /**
     * The label values of {@see self::createProduct()}
     */
    public const PRODUCT_LABEL = ['guaranteeMonths' => 36, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    public static function createResolver(): GaranLabelResolver
    {
        // resolving the label values never renders a template
        return new GaranLabelResolver(
            new GaranLabelDurationFormatter(),
            new GaranLabelRenderer(new Environment(new ArrayLoader())),
        );
    }

    public static function createProduct(string $id): ProductEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $product = new ProductEntity();
        $product->setId($id);
        $product->setManufacturer($manufacturer);
        $product->setManufacturerNumber('ACME-36');
        $product->setGuaranteeMonths(36);
        $product->setGuaranteeConfirmed(true);

        return $product;
    }
}
