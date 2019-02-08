<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Listing\ListingSortingEntity;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
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
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $sortingKey = $event->getRequest()->optionalGet(self::SORTING_PARAMETER);
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
}
