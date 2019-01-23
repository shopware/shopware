<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReverseInherited;
use Shopware\Core\System\Currency\CurrencyDefinition;

class ProductPriceRuleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product_price_rule';
    }

    public static function getCollectionClass(): string
    {
        return ProductPriceRuleCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ProductPriceRuleEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required()),
            (new PriceField('price', 'price'))->addFlags(new Required()),
            (new IntField('quantity_start', 'quantityStart'))->addFlags(new Required()),
            new IntField('quantity_end', 'quantityEnd'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, false, 'id'))->addFlags(new ReverseInherited('priceRules')),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, false),
        ]);
    }
}
