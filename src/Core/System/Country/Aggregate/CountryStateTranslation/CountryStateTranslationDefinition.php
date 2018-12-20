<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

class CountryStateTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'country_state_translation';
    }

    public static function getCollectionClass(): string
    {
        return CountryStateTranslationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CountryStateTranslationEntity::class;
    }

    public static function getDefinitionClass(): string
    {
        return CountryStateDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
        ]);
    }
}
