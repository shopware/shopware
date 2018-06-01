<?php declare(strict_types=1);

namespace Shopware\System\Currency\Aggregate\CurrencyTranslation;

use Shopware\System\Language\LanguageDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationDetailCollection;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationDeletedEvent;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationWrittenEvent;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationBasicStruct;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationDetailStruct;
use Shopware\System\Currency\CurrencyDefinition;

class CurrencyTranslationDefinition extends EntityDefinition
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
        return 'currency_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(CurrencyDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('short_name', 'shortName'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CurrencyTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CurrencyTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CurrencyTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CurrencyTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CurrencyTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CurrencyTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CurrencyTranslationDetailCollection::class;
    }
}
