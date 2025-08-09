<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class PagingListingProcessor extends AbstractListingProcessor
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $limit = $this->getLimit($criteria, $context);

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

        $limit = $result->getCriteria()->getLimit() ?? $this->getLimit($result->getCriteria(), $context);
        $result->setLimit($limit);
    }

    private function getLimit(Criteria $criteria, SalesChannelContext $context): int
    {
        if ($criteria->getLimit() !== null && $criteria->getLimit() > 0) {
            return $criteria->getLimit();
        }

        $limit = $this->config->getInt('core.listing.productsPerPage', $context->getSalesChannelId());

        return $limit <= 0 ? 24 : $limit;
    }

    private function getPage(Request $request): ?int
    {
        $page = $request->query->has('p') ? $request->query->getInt('p') : null;
        $page = $request->request->has('p') ? $request->request->getInt('p') : $page;

        return $page > 0 ? $page : null;
    }
}
