<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Currency\CurrencyDefinition;

class CurrencyTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'currency_translation';
    }

    public static function getCollectionClass(): string
    {
        return CurrencyTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CurrencyTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CurrencyDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('short_name', 'shortName'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new AttributesField(),
        ]);
    }
}
