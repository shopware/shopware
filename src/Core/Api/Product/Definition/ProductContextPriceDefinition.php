<?php declare(strict_types=1);

namespace Shopware\Api\Product\Definition;

use Shopware\Api\Context\Definition\ContextRuleDefinition;
use Shopware\System\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\PriceField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Product\Collection\ProductContextPriceBasicCollection;
use Shopware\Api\Product\Collection\ProductContextPriceDetailCollection;
use Shopware\Api\Product\Event\ProductContextPrice\ProductContextPriceDeletedEvent;
use Shopware\Api\Product\Event\ProductContextPrice\ProductContextPriceWrittenEvent;
use Shopware\Api\Product\Repository\ProductContextPriceRepository;
use Shopware\Api\Product\Struct\ProductContextPriceBasicStruct;
use Shopware\Api\Product\Struct\ProductContextPriceDetailStruct;

class ProductContextPriceDefinition extends EntityDefinition
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
        return 'product_context_price';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(CurrencyDefinition::class),
            (new FkField('context_rule_id', 'contextRuleId', ContextRuleDefinition::class))->setFlags(new Required()),
            (new PriceField('price', 'price'))->setFlags(new Required()),
            (new IntField('quantity_start', 'quantityStart'))->setFlags(new Required()),
            new IntField('quantity_end', 'quantityEnd'),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false, 'context_price_join_id'),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, false),
            new ManyToOneAssociationField('contextRule', 'context_rule_id', ContextRuleDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ProductContextPriceRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductContextPriceBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductContextPriceDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductContextPriceWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductContextPriceBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductContextPriceDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductContextPriceDetailCollection::class;
    }
}
