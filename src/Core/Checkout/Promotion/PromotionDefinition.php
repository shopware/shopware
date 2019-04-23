<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PromotionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'promotion';
    }

    public static function getCollectionClass(): string
    {
        return PromotionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return PromotionEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new FloatField('value', 'value'),
            new BoolField('percental', 'percental'),
            new DateField('valid_from', 'validFrom'),
            new DateField('valid_until', 'validUntil'),
            new IntField('redeemable', 'redeemable'),
            new BoolField('exclusive', 'exclusive'),
            new IntField('priority', 'priority'),
            new BoolField('exclude_lower_priority', 'excludeLowerPriority'),
            new FkField('scope_rule_id', 'scopeRuleId', RuleDefinition::class),
            new ManyToOneAssociationField('scopeRule', 'scope_rule_id', RuleDefinition::class, 'id', true),
            new FkField('discount_rule_id', 'discountRuleId', RuleDefinition::class),
            new ManyToOneAssociationField('discountRule', 'discount_rule_id', RuleDefinition::class, 'id', true),
            new StringField('code_type', 'codeType'),
            new StringField('code', 'code'),
            new OneToManyAssociationField('salesChannels', PromotionSalesChannelDefinition::class, 'promotion_id', 'id'),
            new OneToManyAssociationField('discounts', PromotionDiscountDefinition::class, 'promotion_id'),

            new ManyToManyAssociationField('personaRules', RuleDefinition::class, PromotionPersonaRuleDefinition::class, 'promotion_id', 'rule_id'),
            new ManyToManyAssociationField('personaCustomers', CustomerDefinition::class, PromotionPersonaCustomerDefinition::class, 'promotion_id', 'customer_id'),
            new ManyToManyAssociationField('orderRules', RuleDefinition::class, PromotionOrderRuleDefinition::class, 'promotion_id', 'rule_id'),
        ]);
    }
}
