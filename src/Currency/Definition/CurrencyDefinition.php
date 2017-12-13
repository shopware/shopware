<?php declare(strict_types=1);

namespace Shopware\Currency\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Currency\Collection\CurrencyBasicCollection;
use Shopware\Currency\Collection\CurrencyDetailCollection;
use Shopware\Currency\Event\Currency\CurrencyWrittenEvent;
use Shopware\Currency\Repository\CurrencyRepository;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Currency\Struct\CurrencyDetailStruct;
use Shopware\Order\Definition\OrderDefinition;
use Shopware\Shop\Definition\ShopCurrencyDefinition;
use Shopware\Shop\Definition\ShopDefinition;

class CurrencyDefinition extends EntityDefinition
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
        return 'currency';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FloatField('factor', 'factor'))->setFlags(new Required()),
            (new StringField('symbol', 'symbol'))->setFlags(new Required()),
            (new TranslatedField(new StringField('short_name', 'shortName')))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('is_default', 'isDefault'),
            new IntField('symbol_position', 'symbolPosition'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', CurrencyTranslationDefinition::class, 'currency_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_uuid', false, 'uuid'),
            new ManyToManyAssociationField('shops', ShopDefinition::class, ShopCurrencyDefinition::class, false, 'currency_uuid', 'shop_uuid', 'shopUuids'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CurrencyRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CurrencyBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CurrencyWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CurrencyBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CurrencyTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CurrencyDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CurrencyDetailCollection::class;
    }
}
