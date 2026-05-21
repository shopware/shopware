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
#[UcpMcpTool(name: 'get_product', capability: CatalogLookupCapability::NAME, description: 'Get one product by id')]
#[Package('framework')]
class GetProductTool extends AbstractUcpMcpTool
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
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'string'],
                'selected' => ['type' => 'array'],
                'preferences' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('catalog_lookup.json', 'get_product_response');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            return ['error' => ['code' => 'invalid_argument', 'message' => 'id is required']];
        }

        $filters = [new EqualsAnyFilter('productNumber', [$id])];
        if (Uuid::isValid($id)) {
            $filters[] = new EqualsAnyFilter('id', [$id]);
        }

        $criteria = (new Criteria())
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters))
            ->addAssociation('manufacturer')
            ->addAssociation('media.media')
            ->setLimit(1);

        $entity = $this->productRepository->search($criteria, $context->salesChannelContext)->first();
        if (!$entity instanceof SalesChannelProductEntity) {
            return ['error' => ['code' => 'product_not_found', 'message' => 'Product was not found.']];
        }

        return ['product' => $this->productMapper->toUcpProduct($entity, $context->salesChannelContext)];
    }
}
