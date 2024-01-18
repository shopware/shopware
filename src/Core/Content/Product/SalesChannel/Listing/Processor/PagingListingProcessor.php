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
        $limit = $this->getLimit($criteria, $request, $context);

        $page = $this->getPage($request);

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $result->setPage($this->getPage($request));

        $limit = $result->getCriteria()->getLimit() ?? $this->getLimit($result->getCriteria(), $request, $context);
        $result->setLimit($limit);
    }

    private function getLimit(Criteria $criteria, Request $request, SalesChannelContext $context): int
    {
        $limit = $request->query->getInt('limit');

        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $request->request->getInt('limit', $limit);
        }

        // request > criteria > config
        if ($limit > 0) {
            return $limit;
        }

        if ($criteria->getLimit() !== null) {
            return $criteria->getLimit();
        }

        $limit = $this->config->getInt('core.listing.productsPerPage', $context->getSalesChannelId());

        return $limit <= 0 ? 24 : $limit;
    }

    private function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }
}
