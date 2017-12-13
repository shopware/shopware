<?php declare(strict_types=1);

namespace Shopware\Shop\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Currency\Definition\CurrencyDefinition;
use Shopware\Shop\Event\ShopCurrency\ShopCurrencyWrittenEvent;

class ShopCurrencyDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    public static function getEntityName(): string
    {
        return 'shop_currency';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        return self::$fields = new FieldCollection([
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('currency_uuid', 'currencyUuid', CurrencyDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, false),
            new ManyToOneAssociationField('currency', 'currency_uuid', CurrencyDefinition::class, false),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own repositories');
    }

    public static function getBasicCollectionClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own collection classes');
    }

    public static function getBasicStructClass(): string
    {
        throw new \RuntimeException('Mapping table do not have own struct classes');
    }

    public static function getWrittenEventClass(): string
    {
        return ShopCurrencyWrittenEvent::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }
}
