<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;

class SalesChannelTypeTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_type_translation';
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelTypeTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalesChannelTypeTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return SalesChannelTypeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('manufacturer', 'manufacturer'),
            new StringField('description', 'description'),
            new LongTextWithHtmlField('description_long', 'descriptionLong'),
        ]);
    }
}
