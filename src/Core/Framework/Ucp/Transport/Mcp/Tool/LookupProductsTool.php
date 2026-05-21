<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogLookupCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductMapper;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'lookup_catalog', capability: CatalogLookupCapability::NAME, description: 'Look up products by id')]
#[Package('framework')]
class LookupProductsTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly ProductMapper $productMapper,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ids'],
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 1,
                    'maxItems' => 50,
                    'description' => 'Product IDs to fetch',
                ],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('catalog_lookup.json', 'lookup_response');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $sc = $context->salesChannelContext;
        $ids = $arguments['ids'] ?? [];
        if (!\is_array($ids) || $ids === []) {
            return ['products' => []];
        }

        $stringIds = array_values(array_filter($ids, 'is_string'));
        $filters = [new EqualsAnyFilter('productNumber', $stringIds)];
        $uuidIds = array_values(array_filter($stringIds, static fn (string $id): bool => Uuid::isValid($id)));
        if ($uuidIds !== []) {
            $filters[] = new EqualsAnyFilter('id', $uuidIds);
        }

        $criteria = (new Criteria())
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters))
            ->addAssociation('manufacturer')
            ->addAssociation('media.media');

        $result = $this->productRepository->search($criteria, $sc);

        $products = [];
        foreach ($result as $entity) {
            if ($entity instanceof SalesChannelProductEntity) {
                $products[] = $this->productMapper->toUcpProduct($entity, $sc);
            }
        }

        return ['products' => $products];
    }
}
