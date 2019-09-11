<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Extension;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Framework\Seo\Entity\Field\SeoUrlAssociationField;
use Shopware\Storefront\Framework\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

class ProductExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new SeoUrlAssociationField('seoUrls', ProductPageSeoUrlRoute::ROUTE_NAME, 'id')
        );
        $collection->add(
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'product_id'))
                ->addFlags(new CascadeDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
