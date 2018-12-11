<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Seo\DbalIndexing\SeoUrl\DetailPageSeoUrlIndexer;
use Shopware\Storefront\Seo\Entity\Field\CanonicalUrlAssociationField;

class CanonicalUrlExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new CanonicalUrlAssociationField('canonicalUrl', 'id', true, DetailPageSeoUrlIndexer::ROUTE_NAME)
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
