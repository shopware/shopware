<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTheme\SalesChannelThemeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('salesChannelTheme', 'id', 'sales_channel_id', SalesChannelThemeDefinition::class, true))
                ->addFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }
}
