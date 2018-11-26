<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SeoUrlDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'seo_url';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IdField('foreign_key', 'foreignKey'))->setFlags(new Required()),
            (new StringField('path_info', 'pathInfo'))->setFlags(new Required()),
            (new StringField('seo_path_info', 'seoPathInfo'))->setFlags(new Required()),
            new BoolField('is_canonical', 'isCanonical'),
            new BoolField('is_modified', 'isModified'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return SeoUrlCollection::class;
    }

    public static function getStructClass(): string
    {
        return SeoUrlStruct::class;
    }
}
