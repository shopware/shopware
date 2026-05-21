<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Catalog;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP Catalog REST binding. Implements both `dev.ucp.shopping.catalog.search`
 * and `dev.ucp.shopping.catalog.lookup`, delegating to the existing Store-API
 * product search route and the product repository, respectively.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class CatalogController
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 100;

    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly SalesChannelRepository $productRepository,
        private readonly ProductMapper $productMapper,
        private readonly CursorCodec $cursorCodec,
    ) {
    }

    #[Route(path: '/ucp/v1/catalog/search', name: 'ucp.catalog.search', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request, CatalogSearchCapability::NAME);
        $payload = $this->decodeBody($request);

        $query = isset($payload['query']) && \is_string($payload['query']) ? $payload['query'] : '';
        $filters = isset($payload['filters']) && \is_array($payload['filters']) ? $payload['filters'] : [];
        $pageSize = $this->resolvePageSize($payload);
        $queryFingerprint = $this->cursorCodec->fingerprint($query, $filters);

        // Pagination resolution: cursor takes precedence over the legacy
        // limit/offset shape, but both are accepted so existing clients keep
        // working through one release cycle. New clients should send
        // `cursor` only.
        $cursorState = null;
        $cursorIn = $payload['cursor'] ?? null;
        if (\is_string($cursorIn) && $cursorIn !== '') {
            $cursorState = $this->cursorCodec->decode($cursorIn, $queryFingerprint);
        }

        $hasQuery = $query !== '';
        $mode = $hasQuery ? CursorCodec::MODE_PAGE : CursorCodec::MODE_AFTER;

        if ($cursorState !== null && $cursorState['mode'] !== $mode) {
            throw UcpException::invalidCursor('cursor mode does not match request type');
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
        } elseif (isset($payload['offset']) && \is_numeric($payload['offset'])) {
            // Legacy limit/offset path. Only honoured when no cursor was sent
            // — a request that mixes cursor+offset is treated as cursor-only.
            $offset = max(0, (int) $payload['offset']);
            if ($mode === CursorCodec::MODE_PAGE) {
                $page = (int) floor($offset / max(1, $pageSize)) + 1;
            }
        }

        // Lookahead trick: ask the data layer for one extra row so we can
        // compute `has_more` deterministically without a second round-trip.
        $effectiveLimit = $pageSize + 1;

        // ProductSearchRoute reads `search` from the Symfony InputBag and
        // honours `limit`/`p` only when not provided on the criteria. We
        // therefore put pagination state on the criteria exclusively to
        // avoid a double-offset bug (`p=N, limit=L` causes the route to
        // re-derive offset = (N-1)*L and ignore our explicit setOffset).
        $request->query = new InputBag(array_filter([
            'search' => $query,
        ]));

        $criteria = new Criteria();
        $criteria->setLimit($effectiveLimit);
        if ($mode === CursorCodec::MODE_PAGE) {
            $criteria->setOffset($offset);
        }

        if (isset($filters['price']) && \is_array($filters['price'])) {
            $price = $filters['price'];
            $min = isset($price['min']) ? (float) $price['min'] / 100 : null;
            $max = isset($price['max']) ? (float) $price['max'] / 100 : null;
            if ($min !== null || $max !== null) {
                $criteria->addFilter(new RangeFilter('price', array_filter([
                    'gte' => $min,
                    'lte' => $max,
                ], static fn ($v): bool => $v !== null)));
            }
        }

        if (isset($filters['category_id']) && \is_string($filters['category_id'])) {
            $criteria->addFilter(new EqualsAnyFilter('categoryIds', [$filters['category_id']]));
        }

        // For non-search browsing we drive an opaque cursor by `id ASC`
        // ordering plus an `id > <after>` filter. UUID-hex sorts
        // lexicographically and is stable for the duration of a paginated
        // walk, which is all the cursor needs to guarantee.
        if ($mode === CursorCodec::MODE_AFTER) {
            $criteria->resetSorting();
            $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
            if ($afterId !== null) {
                $criteria->addFilter(new RangeFilter('id', ['gt' => $afterId]));
            }
        }

        $response = $this->productSearchRoute->load($request, $context->salesChannelContext, $criteria);
        // 6.7 returns ProductSearchRouteResponse::getListing(), 6.8 renamed to getListingResult();
        // both return a ProductListingResult, so we narrow the type for static analysis.
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
            fn (SalesChannelProductEntity $entity): array => $this->productMapper->toUcpProduct($entity, $context->salesChannelContext),
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

        return new JsonResponse([
            'products' => $products,
            'page' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'page_size' => $pageSize,
            ],
            // Legacy block — kept for one release cycle so existing clients
            // do not break the day they upgrade. Total is the unbounded total
            // for `page` mode and `null` for `after` mode (where reporting a
            // cumulative total has no defined meaning).
            'pagination' => [
                'total' => $mode === CursorCodec::MODE_PAGE ? $listing->getTotal() : null,
                'limit' => $pageSize,
                'offset' => $mode === CursorCodec::MODE_PAGE ? $offset : null,
            ],
        ]);
    }

    #[Route(path: '/ucp/v1/catalog/lookup', name: 'ucp.catalog.lookup', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function lookup(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request, CatalogLookupCapability::NAME);
        $payload = $this->decodeBody($request);

        $ids = $payload['ids'] ?? [];
        if (!\is_array($ids) || $ids === []) {
            return new JsonResponse(['products' => []]);
        }

        $criteria = (new Criteria())
            ->addFilter($this->productIdentifierFilter(array_values(array_filter($ids, 'is_string'))))
            ->addAssociation('manufacturer')
            ->addAssociation('media.media');

        $result = $this->productRepository->search($criteria, $context->salesChannelContext);

        $products = [];
        foreach ($result as $entity) {
            if ($entity instanceof SalesChannelProductEntity) {
                $products[] = $this->productMapper->toUcpProduct($entity, $context->salesChannelContext);
            }
        }

        return new JsonResponse(['products' => $products]);
    }

    #[Route(path: '/ucp/v1/catalog/product', name: 'ucp.catalog.product', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function getProduct(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request, CatalogLookupCapability::NAME);
        $payload = $this->decodeBody($request);

        $id = $payload['id'] ?? null;
        if (!\is_string($id) || $id === '') {
            throw UcpException::invalidArgument('catalog/product requires an `id` field');
        }

        $criteria = (new Criteria())
            ->addFilter($this->productIdentifierFilter([$id]))
            ->addAssociation('manufacturer')
            ->addAssociation('media.media')
            ->setLimit(1);

        $entity = $this->productRepository->search($criteria, $context->salesChannelContext)->first();
        if (!$entity instanceof SalesChannelProductEntity) {
            return new JsonResponse([
                'error' => [
                    'code' => 'product_not_found',
                    'message' => 'Product was not found.',
                ],
            ], 404);
        }

        return new JsonResponse([
            'product' => $this->productMapper->toUcpProduct($entity, $context->salesChannelContext),
        ]);
    }

    /**
     * Resolve the requested page size from the payload, supporting both
     * `page_size` (cursor-style requests) and the legacy `limit` field.
     * Caps at {@see MAX_PAGE_SIZE} so a client cannot exhaust the database
     * with a single request, and floors at 1.
     *
     * @param array<string, mixed> $payload
     */
    private function resolvePageSize(array $payload): int
    {
        $candidate = $payload['page_size'] ?? $payload['limit'] ?? null;
        if (!\is_numeric($candidate)) {
            return self::DEFAULT_PAGE_SIZE;
        }

        $value = (int) $candidate;
        if ($value < 1) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return min(self::MAX_PAGE_SIZE, $value);
    }

    private function resolveContext(Request $request, string $capability): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }
        if (!$context->intersection->has($capability)) {
            throw UcpException::capabilityNotEnabled($capability);
        }

        return $context;
    }

    /**
     * @param list<string> $ids
     */
    private function productIdentifierFilter(array $ids): MultiFilter
    {
        $filters = [new EqualsAnyFilter('productNumber', $ids)];
        $uuidIds = array_values(array_filter($ids, static fn (string $id): bool => Uuid::isValid($id)));
        if ($uuidIds !== []) {
            $filters[] = new EqualsAnyFilter('id', $uuidIds);
        }

        return new MultiFilter(MultiFilter::CONNECTION_OR, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
