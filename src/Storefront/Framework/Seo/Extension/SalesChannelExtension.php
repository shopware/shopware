<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Framework\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;

class SalesChannelExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'sales_channel_id', 'id')
        );
        $collection->add(
            new OneToManyAssociationField('seoUrlTemplates', SeoUrlTemplateDefinition::class, 'sales_channel_id')
        );
        $collection->add(
            new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'sales_channel_id')
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }
}
