<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection\ProductPriceRuleBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection\ProductPriceRuleDetailCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event\ProductPriceRuleDeletedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event\ProductPriceRuleWrittenEvent;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleBasicStruct;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleDetailStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\PriceField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Currency\CurrencyDefinition;

class ProductPriceRuleDefinition extends EntityDefinition
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
        return 'product_price_rule';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ProductDefinition::class),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(CurrencyDefinition::class),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->setFlags(new Required()),
            (new PriceField('price', 'price'))->setFlags(new Required()),
            (new IntField('quantity_start', 'quantityStart'))->setFlags(new Required()),
            new IntField('quantity_end', 'quantityEnd'),
            (new DateField('created_at', 'createdAt'))->setFlags(new Required()),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false, 'context_price_join_id'),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, false),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return ProductPriceRuleRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ProductPriceRuleBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ProductPriceRuleDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ProductPriceRuleWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ProductPriceRuleBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ProductPriceRuleDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ProductPriceRuleDetailCollection::class;
    }
}
