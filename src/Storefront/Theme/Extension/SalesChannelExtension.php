<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopware\Storefront\Theme\ThemeDefinition;

#[Package('storefront')]
class SalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField('themes', ThemeDefinition::class, ThemeSalesChannelDefinition::class, 'sales_channel_id', 'theme_id')
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }
}
