<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Listing\ListingSortingEntity;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
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
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $sortingKey = $request->optionalGet(self::SORTING_PARAMETER);
        if (!$sortingKey) {
            return;
        }

        $search = new Criteria();
        $search->addFilter(new EqualsFilter('listing_sorting.uniqueKey', $sortingKey));
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
            $event->getRequest()->optionalGet(self::SORTING_PARAMETER)
        );
    }
}
