<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('inventory')]
class ProductDocumentSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageCriteriaEvent::class => 'addProductDocuments',
        ];
    }

    public function addProductDocuments(ProductPageCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria()->getAssociation('productDocuments');
        $criteria->addAssociation('media');
        $criteria->addSorting(new FieldSorting('position'));
    }
}
