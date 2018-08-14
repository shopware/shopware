<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCatalog;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelCatalogDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_catalog';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('catalog_id', 'catalog_id', CatalogDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
            new ManyToOneAssociationField('catalog', 'catalog_id', CatalogDefinition::class, false),
        ]);
    }
}
