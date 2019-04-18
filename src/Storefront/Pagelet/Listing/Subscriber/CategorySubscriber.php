<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CategorySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        if ($categoryId = $request->get('categoryId')) {
            $event->getCriteria()->addFilter(new EqualsFilter('product.categoriesRo.id', $categoryId));
        }
    }
}
