<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Country\CountryDefinition;

class CountryTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'country_translation';
    }

    public static function getCollectionClass(): string
    {
        return CountryTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CountryTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CountryDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
