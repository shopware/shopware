<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogSearchCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CursorCodec;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductMapper;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'search_catalog', capability: CatalogSearchCapability::NAME, description: 'Search the merchant\'s product catalog')]
#[Package('framework')]
class SearchCatalogTool extends AbstractUcpMcpTool
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly ProductMapper $productMapper,
        private readonly CursorCodec $cursorCodec,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Free-text search query'],
                'cursor' => ['type' => 'string', 'description' => 'Opaque cursor returned in `page.next_cursor` of the previous response. Mutually exclusive with offset.'],
                'page_size' => ['type' => 'integer', 'default' => 20, 'maximum' => 100, 'minimum' => 1],
                'limit' => ['type' => 'integer', 'description' => 'Legacy alias for `page_size`. Prefer `page_size` for new clients.', 'maximum' => 100, 'minimum' => 1],
                'offset' => ['type' => 'integer', 'description' => 'Legacy offset; ignored when `cursor` is set.', 'minimum' => 0],
                'filters' => [
                    'type' => 'object',
                    'properties' => [
                        'price' => [
                            'type' => 'object',
                            'properties' => [
                                'min' => ['type' => 'integer', 'description' => 'Minimum price in minor units'],
                                'max' => ['type' => 'integer', 'description' => 'Maximum price in minor units'],
                            ],
                        ],
                        'category_id' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('catalog_search.json', 'search_response');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $sc = $context->salesChannelContext;
        $query = \is_string($arguments['query'] ?? null) ? $arguments['query'] : '';
        $filters = isset($arguments['filters']) && \is_array($arguments['filters']) ? $arguments['filters'] : [];
        $pageSize = $this->resolvePageSize($arguments);

        $queryFingerprint = $this->cursorCodec->fingerprint($query, $filters);
        $hasQuery = $query !== '';
        $mode = $hasQuery ? CursorCodec::MODE_PAGE : CursorCodec::MODE_AFTER;

        $cursorIn = $arguments['cursor'] ?? null;
        $cursorState = null;
        if (\is_string($cursorIn) && $cursorIn !== '') {
            $cursorState = $this->cursorCodec->decode($cursorIn, $queryFingerprint);
            if ($cursorState['mode'] !== $mode) {
                throw UcpException::invalidCursor('cursor mode does not match request type');
            }
        }

        $page = 1;
        $offset = 0;
        $afterId = null;

        if ($cursorState !== null) {
            if ($mode === CursorCodec::MODE_PAGE) {
                $page = max(1, $cursorState['page'] ?? 1);
                $offset = ($page - 1) * $pageSize;
            } else {
                $afterId = $cursorState['after'];
            }
        } elseif (isset($arguments['offset']) && \is_numeric($arguments['offset'])) {
            $offset = max(0, (int) $arguments['offset']);
            if ($mode === CursorCodec::MODE_PAGE) {
                $page = (int) floor($offset / max(1, $pageSize)) + 1;
            }
        }

        $effectiveLimit = $pageSize + 1;

        // Pagination state lives on the criteria exclusively — see
        // CatalogController::search() for the rationale.
        $request = new Request();
        $request->query = new InputBag([
            'search' => $query,
        ]);

        $criteria = new Criteria();
        $criteria->setLimit($effectiveLimit);
        if ($mode === CursorCodec::MODE_PAGE) {
            $criteria->setOffset($offset);
        } else {
            $criteria->resetSorting();
            $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
            if ($afterId !== null) {
                $criteria->addFilter(new RangeFilter('id', ['gt' => $afterId]));
            }
        }

        $response = $this->productSearchRoute->load($request, $sc, $criteria);
        // Same shim as CatalogController::search() — narrow for static analysis.
        $listing = method_exists($response, 'getListing')
            ? $response->getListing()
            : $response->getListingResult();
        \assert($listing instanceof ProductListingResult);

        $entities = [];
        foreach ($listing->getEntities() as $entity) {
            if ($entity instanceof SalesChannelProductEntity) {
                $entities[] = $entity;
            }
        }

        $hasMore = \count($entities) > $pageSize;
        if ($hasMore) {
            $entities = \array_slice($entities, 0, $pageSize);
        }

        $products = array_map(
            fn (SalesChannelProductEntity $entity): array => $this->productMapper->toUcpProduct($entity, $sc),
            $entities
        );

        $nextCursor = null;
        if ($hasMore && $entities !== []) {
            if ($mode === CursorCodec::MODE_PAGE) {
                $nextCursor = $this->cursorCodec->encode([
                    'mode' => CursorCodec::MODE_PAGE,
                    'page' => $page + 1,
                    'q' => $queryFingerprint,
                ]);
            } else {
                $last = end($entities);
                $nextCursor = $this->cursorCodec->encode([
                    'mode' => CursorCodec::MODE_AFTER,
                    'after' => $last->getId(),
                    'q' => $queryFingerprint,
                ]);
            }
        }

        return [
            'products' => $products,
            'page' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'page_size' => $pageSize,
            ],
            'pagination' => [
                'total' => $mode === CursorCodec::MODE_PAGE ? $listing->getTotal() : null,
                'limit' => $pageSize,
                'offset' => $mode === CursorCodec::MODE_PAGE ? $offset : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function resolvePageSize(array $arguments): int
    {
        $candidate = $arguments['page_size'] ?? $arguments['limit'] ?? null;
        if (!\is_numeric($candidate)) {
            return self::DEFAULT_PAGE_SIZE;
        }

        $value = (int) $candidate;
        if ($value < 1) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return min(self::MAX_PAGE_SIZE, $value);
    }
}
