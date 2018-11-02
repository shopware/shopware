<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Category;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;

class CanonicalUrlExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new CanonicalUrlAssociationField('canonicalUrl', 'id', true, ListingPageSeoUrlIndexer::ROUTE_NAME)
        );
    }

    public function getDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }
}
