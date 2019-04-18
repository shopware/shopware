<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ManufacturerAggregationSubscriber implements EventSubscriberInterface
{
    public const PRODUCT_MANUFACTURER_ID = 'product.manufacturer.id';

    public const MANUFACTURER_PARAMETER = self::AGGREGATION_NAME;

    public const AGGREGATION_NAME = 'manufacturer';

    /**
     * @var EntityRepositoryInterface
     */
    private $manufacturerRepository;

    public function __construct(EntityRepositoryInterface $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::PRODUCT_MANUFACTURER_ID,
                ProductManufacturerDefinition::class,
                self::MANUFACTURER_PARAMETER
            )
        );

        $names = $request->query->get(self::MANUFACTURER_PARAMETER);
        if (empty($names)) {
            return;
        }
        $names = array_filter(explode('|', $names));

        $search = new Criteria();
        $search->addFilter(new EqualsAnyFilter('product_manufacturer.name', $names));
        $ids = $this->manufacturerRepository->searchIds($search, $event->getContext());

        if (empty($ids->getIds())) {
            return;
        }

        $query = new EqualsAnyFilter(self::PRODUCT_MANUFACTURER_ID, $ids->getIds());

        $event->getCriteria()->addPostFilter($query);
    }
}
