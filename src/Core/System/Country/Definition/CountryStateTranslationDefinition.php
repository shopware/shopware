<?php declare(strict_types=1);

namespace Shopware\System\Country\Definition;

use Shopware\System\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\System\Country\Collection\CountryStateTranslationDetailCollection;
use Shopware\System\Country\Event\CountryStateTranslation\CountryStateTranslationDeletedEvent;
use Shopware\System\Country\Event\CountryStateTranslation\CountryStateTranslationWrittenEvent;
use Shopware\System\Country\Repository\CountryStateTranslationRepository;
use Shopware\System\Country\Struct\CountryStateTranslationBasicStruct;
use Shopware\System\Country\Struct\CountryStateTranslationDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;

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
