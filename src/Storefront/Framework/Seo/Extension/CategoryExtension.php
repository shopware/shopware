<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Extension;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Framework\Seo\Entity\Field\SeoUrlAssociationField;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

class CategoryExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new SeoUrlAssociationField('seoUrls', NavigationPageSeoUrlRoute::ROUTE_NAME, 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }
}
