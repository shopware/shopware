<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<PropertyGroupCollection>
 */
#[Package('discovery')]
class ProductConfiguratorDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'product_configurator';

    public function __construct(private readonly ProductConfiguratorLoader $configuratorLoader)
    {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification(
                'productProperty',
                ConfigKeyKind::PropertyReference,
                'string',
                required: false,
                hasDefault: true,
                default: 'product',
                referencedType: 'object'
            ),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $product = $inputs->get('productProperty');
        if (!$product instanceof SalesChannelProductEntity) {
            return ContentDataLoaderResult::notFound();
        }

        $parentId = $product->getParentId();

        // Product detail layouts may receive the parent product when no concrete variant was selected yet.
        // ProductConfiguratorLoader intentionally serves the legacy buy widget only for a child variant, so
        // use a copy with the parent id as the configurator root for this standalone Content System component.
        if ($parentId === null) {
            $parentId = $product->getId();

            $product = clone $product;
            $product->setParentId($parentId);
        }

        try {
            $groups = $this->configuratorLoader->load($product, $context);
        } catch (InconsistentCriteriaIdsException) {
            return ContentDataLoaderResult::notFound();
        }

        return ContentDataLoaderResult::cached($groups, EntityCacheKeyGenerator::buildProductTag($parentId));
    }
}
