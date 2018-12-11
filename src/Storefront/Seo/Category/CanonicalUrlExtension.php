<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo\Category;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Seo\DbalIndexing\SeoUrl\ListingPageSeoUrlIndexer;
use Shopware\Storefront\Seo\Entity\Field\CanonicalUrlAssociationField;

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
