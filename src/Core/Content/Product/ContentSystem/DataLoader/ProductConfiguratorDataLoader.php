<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorProductData;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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

    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly PartialProductConfiguratorLoader $configuratorLoader,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification(
                'productId',
                ConfigKeyKind::PropertyReference,
                'string',
                required: false,
                hasDefault: true,
                default: 'productId',
            ),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $productId = $inputs->stringOrNull('productId');
        if ($productId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $criteria = (new Criteria([$productId]))->addFields([
            'id',
            'parentId',
            'optionIds',
            'variantListingConfig',
        ]);

        /** @var PartialEntity|null $entity */
        $entity = $this->productRepository->search($criteria, $context)->getEntities()->first();
        if (!$entity instanceof PartialEntity) {
            return ContentDataLoaderResult::notFound();
        }

        /** @var ?string $parentId */
        $parentId = $entity->get('parentId');
        /** @var ?array<string> $optionIds */
        $optionIds = $entity->get('optionIds');
        /** @var ?VariantListingConfig $variantListingConfig */
        $variantListingConfig = $entity->get('variantListingConfig');

        $parentId ??= $entity->getId();
        $productData = new ProductConfiguratorProductData(
            $entity->getId(),
            $parentId,
            $optionIds,
            $variantListingConfig,
        );

        return ContentDataLoaderResult::cached(
            $this->configuratorLoader->load($productData, $context),
            EntityCacheKeyGenerator::buildProductTag($parentId)
        );
    }
}
