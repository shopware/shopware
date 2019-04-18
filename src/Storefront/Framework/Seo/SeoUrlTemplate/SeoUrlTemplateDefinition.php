<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SeoUrlTemplateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'seo_url_template';
    }

    public static function getEntityClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return SeoUrlTemplateCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),

            (new StringField('entity_name', 'entityName', 64))->addFlags(new Required()),
            (new StringField('route_name', 'routeName'))->addFlags(new Required()),
            (new StringField('template', 'template'))->addFlags(new Required()),

            new BoolField('is_valid', 'isValid'),

            new CreatedAtField(),
            new UpdatedAtField(),

            new AttributesField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
