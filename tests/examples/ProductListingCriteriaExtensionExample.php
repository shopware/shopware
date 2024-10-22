<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class ProductListingCriteriaExtensionExample implements EventSubscriberInterface
{
    public function __construct(
        // you can inject your own services
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product.listing.criteria.post' => 'modifyCriteria',
        ];
    }

    public function modifyCriteria(ProductListingCriteriaExtension $event): void
    {
        $criteria = $event->criteria;

        // Modify criteria here as needed
        $criteria->resetFilters();

        $event->result = $criteria;
    }
}
