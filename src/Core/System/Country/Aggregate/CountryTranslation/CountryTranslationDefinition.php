<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Language\LanguageDefinition;

class CountryTranslationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'country_translation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CountryTranslationCollection::class;
    }

    public static function getStructClass(): string
    {
        return CountryTranslationStruct::class;
    }
}
