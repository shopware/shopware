<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalesChannelTranslationEntity::class;
    }
}
