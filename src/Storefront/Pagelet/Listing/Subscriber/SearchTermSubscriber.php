<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchBuilder;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchTermSubscriber implements EventSubscriberInterface
{
    public const TERM_PARAMETER = 'search';

    /**
     * @var SearchBuilder
     */
    private $searchBuilder;

    public function __construct(SearchBuilder $searchBuilder)
    {
        $this->searchBuilder = $searchBuilder;
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

        $term = trim((string) $request->optionalGet(self::TERM_PARAMETER));

        if (empty($term)) {
            return;
        }

        $this->searchBuilder->build(
            $event->getCriteria(),
            $term,
            ProductDefinition::class,
            $event->getContext()
        );
    }
}
