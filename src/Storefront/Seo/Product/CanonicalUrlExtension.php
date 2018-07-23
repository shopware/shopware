<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\DbalIndexing\SeoUrl\DetailPageSeoUrlIndexer;

class CanonicalUrlExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
//        $collection->add(
//            new CanonicalUrlAssociationField('canonicalUrl', 'id', true, DetailPageSeoUrlIndexer::ROUTE_NAME)
//        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
