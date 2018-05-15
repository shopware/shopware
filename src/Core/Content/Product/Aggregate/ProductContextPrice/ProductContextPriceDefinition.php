<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice;

use Shopware\Application\Context\Definition\ContextRuleDefinition;
use Shopware\Content\Product\ProductDefinition;
use Shopware\System\Currency\Definition\CurrencyDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\PriceField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceBasicCollection;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Collection\ProductContextPriceDetailCollection;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Event\ProductContextPriceDeletedEvent;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Event\ProductContextPriceWrittenEvent;

use Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceBasicStruct;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceDetailStruct;

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
