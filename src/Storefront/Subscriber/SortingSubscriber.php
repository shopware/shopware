<?php

namespace Shopware\Storefront\Subscriber;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Listing\Repository\ListingSortingRepository;
use Shopware\Api\Listing\Struct\ListingSortingBasicStruct;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SortingSubscriber implements EventSubscriberInterface
{
    public const SORTING_PARAMETER = 'o';

    /**
     * @var ListingSortingRepository
     */
    private $repository;

    public function __construct(ListingSortingRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria',
            ListingEvents::LISTING_PAGE_LOADED_EVENT => 'buildAggregationView'
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::SORTING_PARAMETER)) {
            return;
        }

        $sort = $request->query->get(self::SORTING_PARAMETER);

        $search = new Criteria();
        $search->addFilter(new TermQuery('listing_sorting.uniqueKey', $sort));
        $sortings = $this->repository->search($search, $event->getContext());

        if ($sortings->count() <= 0) {
            return;
        }

        /** @var ListingSortingBasicStruct $sorting */
        $sorting = $sortings->first();
        foreach ($sorting->getPayload() as $fieldSorting) {
            $event->getCriteria()->addSorting($fieldSorting);
        }
    }

    public function buildAggregationView(ListingPageLoadedEvent $event): void
    {
        $search = new Criteria();
        $sortings = $this->repository->search($search, $event->getContext());
        $event->getPage()->getSortings()->fill($sortings->getElements());

        $currentSorting = $event->getRequest()->query->get(self::SORTING_PARAMETER);
        $event->getPage()->setCurrentSorting($currentSorting);
    }
}