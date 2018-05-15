<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Category;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Content\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Content\Category\Struct\CategoryBasicStruct;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Storefront\Api\Seo\Event\SeoUrl\SeoUrlBasicLoadedEvent;
use Shopware\Storefront\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
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
            new CanonicalUrlAssociationField('canonicalUrl', 'id', true, ListingPageSeoUrlIndexer::ROUTE_NAME)
        );
    }

    public function getDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }

    public static function getSubscribedEvents()
    {
        return [
            CategoryBasicLoadedEvent::NAME => 'categoryBasicLoaded',
        ];
    }

    public function categoryBasicLoaded(CategoryBasicLoadedEvent $event)
    {
        if ($event->getCategories()->count() <= 0) {
            return;
        }

        $urls = $event->getCategories()->map(function (CategoryBasicStruct $category) {
            return $category->getExtension('canonicalUrl');
        });

        $urls = array_filter($urls);

        if (empty($urls)) {
            return;
        }

        $urls = new SeoUrlBasicCollection($urls);

        if ($urls->count() > 0) {
            $this->eventDispatcher->dispatch(SeoUrlBasicLoadedEvent::NAME, new SeoUrlBasicLoadedEvent($urls, $event->getContext()));
        }
    }
}
