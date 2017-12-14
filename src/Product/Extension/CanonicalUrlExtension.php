<?php declare(strict_types=1);

namespace Shopware\Product\Extension;

use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Api\Seo\Repository\SeoUrlRepository;
use Shopware\Storefront\Page\Detail\DetailPageUrlGenerator;
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
        return ProductDefinition::class;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductBasicLoadedEvent::NAME => 'productBasicLoaded',
        ];
    }

    public function productBasicLoaded(ProductBasicLoadedEvent $event)
    {
        if ($event->getProducts()->count() <= 0) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('seo_url.name', DetailPageUrlGenerator::ROUTE_NAME));
        $criteria->addFilter(new TermsQuery('seo_url.foreignKey', $event->getProducts()->getUuids()));
        $criteria->addFilter(new TermQuery('seo_url.isCanonical', 1));

        $urls = $this->seoUrlRepository->search($criteria, $event->getContext());

        foreach ($urls as $url) {
            $product = $event->getProducts()->get($url->getForeignKey());
            $product->addExtension('canonicalUrl', $url);
        }
    }
}
