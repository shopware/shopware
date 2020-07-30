<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ShippingMethodPriceDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'shipping_method_price';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShippingMethodPriceCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShippingMethodPriceEntity::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ShippingMethodDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),
            new FkField('rule_id', 'ruleId', RuleDefinition::class),
            new IntField('calculation', 'calculation'),
            new FkField('calculation_rule_id', 'calculationRuleId', RuleDefinition::class),
            new FloatField('quantity_start', 'quantityStart'),
            new FloatField('quantity_end', 'quantityEnd'),
            new PriceField('currency_price', 'currencyPrice'),
            new CustomFields(),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, 'id', false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            new ManyToOneAssociationField('calculationRule', 'calculation_rule_id', RuleDefinition::class, 'id', false),
        ]);
    }
}
