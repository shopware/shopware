<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Listing\ListingSortingEntity;
use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SortingSubscriber implements EventSubscriberInterface
{
    public const SORTING_PARAMETER = 'o';

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LISTING_PAGELET_LOADED => 'buildPage',
            ListingEvents::LISTING_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageletRequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::SORTING_PARAMETER)) {
            return;
        }

        $event->getListingPageletRequest()->setSortingKey(
            $request->query->get(self::SORTING_PARAMETER)
        );
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $search = new Criteria();
        $search->addFilter(new EqualsFilter('listing_sorting.uniqueKey', $request->getSortingKey()));
        $sortings = $this->repository->search($search, $event->getContext());

        if ($sortings->count() <= 0) {
            return;
        }

        /** @var ListingSortingEntity $sorting */
        $sorting = $sortings->first();
        $criteria = $event->getCriteria();
        foreach ($sorting->getPayload() as $fieldSorting) {
            $criteria->addSorting($fieldSorting);
        }
    }

    public function buildPage(ListingPageletLoadedEvent $event): void
    {
        $search = new Criteria();
        $sortings = $this->repository->search($search, $event->getContext());

        foreach ($sortings as $sort) {
            $event->getPage()->getSortings()->add($sort);
        }

        $event->getPage()->setCurrentSorting(
            $event->getRequest()->getSortingKey()
        );
    }
}
