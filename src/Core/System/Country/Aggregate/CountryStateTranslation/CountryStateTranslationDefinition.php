<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation;

use Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationDetailCollection;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Event\CountryStateTranslationDeletedEvent;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Event\CountryStateTranslationWrittenEvent;
use Shopware\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationRepository;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationBasicStruct;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;

class CountryStateTranslationDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'country_state_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CountryStateDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryStateTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryStateTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CountryStateTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryStateTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryStateTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CountryStateTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryStateTranslationDetailCollection::class;
    }
}
