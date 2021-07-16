<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PromotionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'promotion';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PromotionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PromotionEntity::class;
    }

    /**
     * Gets the default values for new entity instances.
     */
    public function getDefaults(): array
    {
        return [
            'active' => false,
            'exclusive' => false,
            'useCodes' => false,
            'useIndividualCodes' => false,
            'individualCodePattern' => '',
            'useSetGroups' => false,
            'maxRedemptionsGlobal' => null,
            'maxRedemptionsPerCustomer' => null,
            'preventCombination' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new DateTimeField('valid_from', 'validFrom'),
            new DateTimeField('valid_until', 'validUntil'),
            new IntField('max_redemptions_global', 'maxRedemptionsGlobal'),
            new IntField('max_redemptions_per_customer', 'maxRedemptionsPerCustomer'),
            (new BoolField('exclusive', 'exclusive'))->addFlags(new Required()),
            new StringField('code', 'code'),
            (new BoolField('use_codes', 'useCodes'))->addFlags(new Required()),
            (new BoolField('use_individual_codes', 'useIndividualCodes'))->addFlags(new Required()),
            new StringField('individual_code_pattern', 'individualCodePattern'),
            (new BoolField('use_setgroups', 'useSetGroups'))->addFlags(new Required()),
            new BoolField('customer_restriction', 'customerRestriction'),
            (new BoolField('prevent_combination', 'preventCombination'))->addFlags(new Required()),

            (new IntField('order_count', 'orderCount'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('orders_per_customer_count', 'ordersPerCustomerCount'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),

            (new OneToManyAssociationField('setgroups', PromotionSetGroupDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('salesChannels', PromotionSalesChannelDefinition::class, 'promotion_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discounts', PromotionDiscountDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('individualCodes', PromotionIndividualCodeDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('personaRules', RuleDefinition::class, PromotionPersonaRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('personaCustomers', CustomerDefinition::class, PromotionPersonaCustomerDefinition::class, 'promotion_id', 'customer_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('orderRules', RuleDefinition::class, PromotionOrderRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('cartRules', RuleDefinition::class, PromotionCartRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),

            (new TranslationsAssociationField(PromotionTranslationDefinition::class, 'promotion_id'))->addFlags(new Required()),
            new ListField('exclusion_ids', 'exclusionIds', IdField::class),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
        ]);
    }
}
