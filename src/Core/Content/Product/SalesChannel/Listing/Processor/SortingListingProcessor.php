<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Processor;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class SortingListingProcessor extends AbstractListingProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $sortingRepository
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$request->get('order')) {
            $request->request->set('order', $this->getSystemDefaultSorting($context));
        }

        /** @var ProductSortingCollection $sortings */
        $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

        $currentSorting = $this->getCurrentSorting($sortings, $request);

        $criteria->addSorting(
            ...$currentSorting->createDalSorting()
        );

        $criteria->addExtension('sortings', $sortings);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        /** @var ProductSortingCollection $sortings */
        $sortings = $result->getCriteria()->getExtension('sortings');
        $currentSortingKey = $this->getCurrentSorting($sortings, $request)->getKey();

        $result->setSorting($currentSortingKey);

        $result->setAvailableSortings($sortings);
    }

    private function getCurrentSorting(ProductSortingCollection $sortings, Request $request): ProductSortingEntity
    {
        $key = $request->get('order');

        if (!\is_string($key)) {
            throw ProductException::sortingNotFoundException('');
        }

        $sorting = $sortings->getByKey($key);
        if ($sorting !== null) {
            return $sorting;
        }

        throw ProductException::sortingNotFoundException($key);
    }

    private function getAvailableSortings(Request $request, Context $context): ProductSortingCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-listing::load-sortings');
        $availableSortings = $request->get('availableSortings');
        $availableSortingsFilter = [];

        if ($availableSortings) {
            arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
            $availableSortingsFilter = array_keys($availableSortings);

            $criteria->addFilter(new EqualsAnyFilter('key', $availableSortingsFilter));
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'));

        /** @var ProductSortingCollection $sortings */
        $sortings = $this->sortingRepository->search($criteria, $context)->getEntities();

        if ($availableSortings) {
            $sortings->sortByKeyArray($availableSortingsFilter);
        }

        return $sortings;
    }

    private function getSystemDefaultSorting(SalesChannelContext $context): string
    {
        return $this->systemConfigService->getString(
            'core.listing.defaultSorting',
            $context->getSalesChannel()->getId()
        );
    }
}
