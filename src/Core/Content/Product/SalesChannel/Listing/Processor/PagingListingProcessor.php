<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class PagingListingProcessor extends AbstractListingProcessor
{
    public const DEFAULT_LIMIT = 24;
    public const DEFAULT_MAX_LIMIT = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $config,
        private readonly int $maxLimit = self::DEFAULT_MAX_LIMIT
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $limit = $this->getLimit($criteria, $context, $request);

        $page = $this->getPage($request);
        if ($page !== null) {
            $criteria->setOffset(($page - 1) * $limit);
        }
        if ($criteria->getOffset() === null || $criteria->getOffset() < 0) {
            $criteria->setOffset(0);
        }

        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $page = $this->getPage($request);
        if ($page !== null) {
            $result->setPage($page);
        }

        $limit = $result->getCriteria()->getLimit() ?? $this->getLimit($result->getCriteria(), $context, $request);
        $result->setLimit($limit);
    }

    private function getLimit(Criteria $criteria, SalesChannelContext $context, Request $request): int
    {
        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : null;
        $limit = $request->request->has('limit') ? $request->request->getInt('limit') : $limit;

        // Priority 1: Request parameter (body > query)
        if ($limit > 0) {
            return min($limit, $this->maxLimit);
        }

        // Priority 2: Criteria limit (unless it came from static config fallback)
        // When no explicit limit was provided in the request, prefer dynamic system config
        $limit = null;
        if (!$criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST)) {
            $limit = $criteria->getLimit();
        }

        // Priority 3: System config
        if ($limit === null || $limit <= 0) {
            $limit = $this->config->getInt('core.listing.productsPerPage', $context->getSalesChannelId());
        }

        // Priority 4: Default fallback
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        return min($limit, $this->maxLimit);
    }

    private function getPage(Request $request): ?int
    {
        $page = $request->query->has('p') ? $request->query->getInt('p') : null;
        $page = $request->request->has('p') ? $request->request->getInt('p') : $page;

        return $page > 0 ? $page : null;
    }
}
