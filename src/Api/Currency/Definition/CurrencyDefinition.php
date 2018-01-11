<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Definition;

use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Currency\Collection\CurrencyDetailCollection;
use Shopware\Api\Currency\Event\Currency\CurrencyDeletedEvent;
use Shopware\Api\Currency\Event\Currency\CurrencyWrittenEvent;
use Shopware\Api\Currency\Repository\CurrencyRepository;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Currency\Struct\CurrencyDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Shop\Definition\ShopCurrencyDefinition;
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FloatField('factor', 'factor'))->setFlags(new Required()),
            (new StringField('symbol', 'symbol'))->setFlags(new Required()),
            (new TranslatedField(new StringField('short_name', 'shortName')))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('is_default', 'isDefault'),
            new IntField('symbol_position', 'symbolPosition'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', CurrencyTranslationDefinition::class, 'currency_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new ManyToManyAssociationField('shops', ShopDefinition::class, ShopCurrencyDefinition::class, false, 'currency_id', 'shop_id', 'shopIds'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return CurrencyDeletedEvent::class;
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
