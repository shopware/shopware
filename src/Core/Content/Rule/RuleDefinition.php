<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class RuleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'rule';
    }

    public static function getCollectionClass(): string
    {
        return RuleCollection::class;
    }

    public static function getEntityClass(): string
    {
        return RuleEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new IntField('priority', 'priority'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            (new BlobField('payload', 'payload'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE), new Internal()),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new JsonField('module_types', 'moduleTypes'),

            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discountSurcharges', DiscountSurchargeDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productPrices', ProductPriceDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethodPrices', ShippingMethodPriceDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethodPriceCalculations', ShippingMethodPriceDefinition::class, 'calculation_rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'availability_rule_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'availability_rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('personaPromotions', PromotionDefinition::class, 'persona_rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('scopePromotions', PromotionDefinition::class, 'scope_rule_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discountPromotions', PromotionDefinition::class, 'discount_rule_id', 'id'))->addFlags(new CascadeDelete()),

            // Promotion References
            (new ManyToManyAssociationField('personaPromotions', PromotionDefinition::class, PromotionPersonaRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('orderPromotions', PromotionDefinition::class, PromotionOrderRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
