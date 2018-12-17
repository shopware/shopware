<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_translation';
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalesChannelTranslationEntity::class;
    }

    public static function getDefinition(): string
    {
        return SalesChannelDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
