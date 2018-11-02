<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'sales_channel_id', false, 'id'))
                ->setFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }
}
