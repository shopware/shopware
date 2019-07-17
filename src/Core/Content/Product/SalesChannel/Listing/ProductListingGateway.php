<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingGateway implements ProductListingGatewayInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfigService = $systemConfigService;
    }

    public function search(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new ProductAvailableFilter($salesChannelContext->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );

        $this->handleCategoryFilter($request, $criteria, $salesChannelContext);

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $salesChannelContext)
        );

        $result = $this->productRepository->search($criteria, $salesChannelContext);

        $markAsNewDayRange = $this->systemConfigService->get('core.listing.markAsNew');

        $now = new \DateTime();
        /** @var SalesChannelProductEntity $product */
        foreach ($result->getEntities() as $product) {
            $product->setIsNew($product->getReleaseDate() instanceof \DateTimeInterface && $product->getReleaseDate()->diff($now)->days <= $markAsNewDayRange);
        }

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $result, $salesChannelContext)
        );

        return $result;
    }

    private function handleCategoryFilter(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $salesChannelContext
    ): void {
        $navigationId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $params = $request->attributes->get('_route_params');

        if ($params && isset($params['navigationId'])) {
            $navigationId = $params['navigationId'];
        }

        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $navigationId));
    }
}
