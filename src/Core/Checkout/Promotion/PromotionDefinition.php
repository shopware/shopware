<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
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
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'promotion';

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
            'priority' => 1,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of promotion.'),
            new TranslatedField('name'),
            (new BoolField('active', 'active'))->addFlags(new Required())->setDescription('When boolean value is `true`, the promotions are available for selection in the storefront for purchase.'),
            (new DateTimeField('valid_from', 'validFrom'))->setDescription('Date and time from when the promotion code gets valid.'),
            (new DateTimeField('valid_until', 'validUntil'))->setDescription('Date and time until when the promotion code is valid.'),
            (new IntField('max_redemptions_global', 'maxRedemptionsGlobal'))->setDescription('The frequency at which the voucher can be redeemed worldwide.'),
            (new IntField('max_redemptions_per_customer', 'maxRedemptionsPerCustomer'))->setDescription('The frequency at which the voucher can be redeemed worldwide per customer.'),
            (new IntField('priority', 'priority'))->addFlags(new Required())->setDescription('A numerical value to prioritize one of the promotions from the list.'),
            (new BoolField('exclusive', 'exclusive'))->addFlags(new Required())->setDescription('Parameter to exclude the promotion codes on certain products'),
            (new StringField('code', 'code'))->setDescription('Promotion code.'),
            (new BoolField('use_codes', 'useCodes'))->addFlags(new Required())->setDescription('A boolean value that indicates whether the promotion uses code or not.'),
            (new BoolField('use_individual_codes', 'useIndividualCodes'))->addFlags(new Required())->setDescription('Indicates either an individual code or generic code for all users.'),
            (new StringField('individual_code_pattern', 'individualCodePattern'))->setDescription('Promotion code pattern.'),
            (new BoolField('use_setgroups', 'useSetGroups'))->addFlags(new Required())->setDescription('Combine promotions. Promotions that are to be used only on certain products and rest not considered.'),
            (new BoolField('customer_restriction', 'customerRestriction'))->setDescription('Indicates who cannot a use the code.'),
            (new BoolField('prevent_combination', 'preventCombination'))->addFlags(new Required())->setDescription('Indicates which combination of codes are allowed.'),

            (new IntField('order_count', 'orderCount'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('The number of times the promotion was used.'),
            (new JsonField('orders_per_customer_count', 'ordersPerCustomerCount'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('The number of times the customer has used the code.'),

            (new OneToManyAssociationField('setgroups', PromotionSetGroupDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('salesChannels', PromotionSalesChannelDefinition::class, 'promotion_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discounts', PromotionDiscountDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('individualCodes', PromotionIndividualCodeDefinition::class, 'promotion_id'))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('personaRules', RuleDefinition::class, PromotionPersonaRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('personaCustomers', CustomerDefinition::class, PromotionPersonaCustomerDefinition::class, 'promotion_id', 'customer_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('orderRules', RuleDefinition::class, PromotionOrderRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('cartRules', RuleDefinition::class, PromotionCartRuleDefinition::class, 'promotion_id', 'rule_id'))->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'promotion_id'))->addFlags(new SetNullOnDelete()),

            (new TranslationsAssociationField(PromotionTranslationDefinition::class, 'promotion_id'))->addFlags(new Required()),
            (new ListField('exclusion_ids', 'exclusionIds', IdField::class))->setDescription('Unique identity of exclusion.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
        ]);
    }
}
