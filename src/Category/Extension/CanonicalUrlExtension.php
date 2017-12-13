<?php declare(strict_types=1);

namespace Shopware\Category\Extension;

use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Category\Definition\CategoryDefinition;
use Shopware\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Seo\Definition\SeoUrlDefinition;
use Shopware\Seo\Repository\SeoUrlRepository;
use Shopware\Storefront\Page\Listing\ListingPageUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CanonicalUrlExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    /**
     * @var SeoUrlRepository
     */
    private $seoUrlRepository;

    public function __construct(SeoUrlRepository $seoUrlRepository)
    {
        $this->seoUrlRepository = $seoUrlRepository;
    }

    public function extendFields(FieldCollection $collection)
    {
        $collection->add(
            (new ManyToOneAssociationField('canonicalUrl', 'uuid', SeoUrlDefinition::class, true, 'foreign_key'))->setFlags(new Extension(), new Deferred())
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

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.name', ListingPageUrlGenerator::ROUTE_NAME));
        $criteria->addFilter(new TermsQuery('seo_url.foreignKey', $event->getCategories()->getUuids()));
        $criteria->addFilter(new TermQuery('seo_url.isCanonical', 1));

        $urls = $this->seoUrlRepository->search($criteria, $event->getContext());

        foreach ($urls as $url) {
            $category = $event->getCategories()->get($url->getForeignKey());
            $category->addExtension('canonicalUrl', $url);
        }
    }
}
