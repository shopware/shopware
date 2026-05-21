<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Catalog;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Resolves UCP item identifiers to Shopware product UUIDs.
 *
 * UCP-facing item IDs are stable merchant identifiers. For Shopware we expose
 * `productNumber` first so conformance fixtures such as `bouquet_roses` can be
 * used directly, while still accepting internal UUIDs for local simulator runs.
 *
 * @internal
 */
#[Package('framework')]
class ProductIdentifierResolver
{
    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(private readonly SalesChannelRepository $productRepository)
    {
    }

    public function resolveToShopwareId(string $ucpItemId, SalesChannelContext $context): ?string
    {
        if (Uuid::isValid($ucpItemId)) {
            return $ucpItemId;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $ucpItemId))
            ->setLimit(1);

        $product = $this->productRepository->search($criteria, $context)->first();

        return $product instanceof SalesChannelProductEntity ? $product->getId() : null;
    }
}
