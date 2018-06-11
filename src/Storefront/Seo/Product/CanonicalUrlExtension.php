<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\DbalIndexing\SeoUrl\DetailPageSeoUrlIndexer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CanonicalUrlExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function extendFields(FieldCollection $collection)
    {
        $collection->add(
            new CanonicalUrlAssociationField('canonicalUrl', 'id', true, DetailPageSeoUrlIndexer::ROUTE_NAME)
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }

    public static function getSubscribedEvents()
    {
        return [];
    }
}
