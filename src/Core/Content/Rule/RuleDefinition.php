<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroupRule\PromotionSetGroupRuleDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class RuleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return RuleCollection::class;
    }

    public function getEntityClass(): string
    {
        return RuleEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new IntField('priority', 'priority'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            (new BlobField('payload', 'payload'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE), new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class)),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new CustomFields(),
            new JsonField('module_types', 'moduleTypes'),

            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),

            // Reverse Associations not available in sales-channel-api
            (new OneToManyAssociationField('productPrices', ProductPriceDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('shippingMethodPrices', ShippingMethodPriceDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('shippingMethodPriceCalculations', ShippingMethodPriceDefinition::class, 'calculation_rule_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'availability_rule_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'availability_rule_id', 'id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('personaPromotions', PromotionDefinition::class, 'persona_rule_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),

            // Promotion References
            (new ManyToManyAssociationField('personaPromotions', PromotionDefinition::class, PromotionPersonaRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('orderPromotions', PromotionDefinition::class, PromotionOrderRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('cartPromotions', PromotionDefinition::class, PromotionCartRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('promotionDiscounts', PromotionDiscountDefinition::class, PromotionDiscountRuleDefinition::class, 'rule_id', 'discount_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('promotionSetGroups', PromotionSetGroupDefinition::class, PromotionSetGroupRuleDefinition::class, 'rule_id', 'setgroup_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
