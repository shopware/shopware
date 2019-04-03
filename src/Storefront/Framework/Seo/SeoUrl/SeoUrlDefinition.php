<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrl;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SeoUrlDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'seo_url';
    }

    public static function getCollectionClass(): string
    {
        return SeoUrlCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SeoUrlEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            (new IdField('foreign_key', 'foreignKey'))->addFlags(new Required()),

            (new StringField('route_name', 'routeName', 50))->addFlags(new Required()),
            (new StringField('path_info', 'pathInfo', 750))->addFlags(new Required()),
            (new StringField('seo_path_info', 'seoPathInfo', 750))->addFlags(new Required()),

            new BoolField('is_canonical', 'isCanonical'),
            new BoolField('is_modified', 'isModified'),
            new BoolField('is_deleted', 'isDeleted'),
            new BoolField('is_valid', 'isValid'),

            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),

            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
