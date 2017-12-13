<?php declare(strict_types=1);

namespace Shopware\Currency\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Currency\Collection\CurrencyTranslationDetailCollection;
use Shopware\Currency\Event\CurrencyTranslation\CurrencyTranslationWrittenEvent;
use Shopware\Currency\Repository\CurrencyTranslationRepository;
use Shopware\Currency\Struct\CurrencyTranslationBasicStruct;
use Shopware\Currency\Struct\CurrencyTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('currency_uuid', 'currencyUuid', CurrencyDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('short_name', 'shortName'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('currency', 'currency_uuid', CurrencyDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
