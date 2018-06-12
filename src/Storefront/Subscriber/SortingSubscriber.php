<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\System\Listing\ListingSortingStruct;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Event\TransformListingPageRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SortingSubscriber implements EventSubscriberInterface
{
    public const SORTING_PARAMETER = 'o';

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LOADED => 'buildPage',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->query->has(self::SORTING_PARAMETER)) {
            return;
        }

        $event->getListingPageRequest()->setSortingKey(
            $request->query->get(self::SORTING_PARAMETER)
        );
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $search = new Criteria();
        $search->addFilter(new TermQuery('listing_sorting.uniqueKey', $request->getSortingKey()));
        $sortings = $this->repository->search($search, $event->getContext());

        if ($sortings->count() <= 0) {
            return;
        }

        /** @var ListingSortingStruct $sorting */
        $sorting = $sortings->first();
        foreach ($sorting->getPayload() as $fieldSorting) {
            $event->getCriteria()->addSorting($fieldSorting);
        }
    }

    public function buildPage(ListingPageLoadedEvent $event): void
    {
        $search = new Criteria();
        $sortings = $this->repository->search($search, $event->getContext());
        $event->getPage()->getSortings()->fill($sortings->getElements());

        $event->getPage()->setCurrentSorting(
            $event->getRequest()->getSortingKey()
        );
    }
}
