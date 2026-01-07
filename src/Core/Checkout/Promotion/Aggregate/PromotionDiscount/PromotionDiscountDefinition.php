<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionDiscountDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'promotion_discount';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PromotionDiscountEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PromotionDiscountCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'considerAdvancedRules' => false,
        ];
    }

    protected function getParentDefinitionClass(): ?string
    {
        return PromotionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of promotion discount.'),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class, 'id'))->addFlags(new Required())->setDescription('Unique identity of promotion.'),
            (new StringField('scope', 'scope'))->addFlags(new Required())->setDescription('Cart or shipping cost.'),
            (new StringField('type', 'type', 32))->addFlags(new Required())->setDescription('Discount is either `absolute` or `percentage`.'),
            (new FloatField('value', 'value'))->addFlags(new Required())->setDescription('To filter by PromotionDiscount value.'),
            (new BoolField('consider_advanced_rules', 'considerAdvancedRules'))->addFlags(new Required())->setDescription('When boolean value is `true`, the promotion discount is applied along with advanced rules.'),
            (new FloatField('max_value', 'maxValue'))->setDescription('Discount in terms of absolute value.'),

            (new StringField('sorter_key', 'sorterKey', 32))->setDescription('Price from `low to high` or `high to low` to sort the product accordingly.'),
            (new StringField('applier_key', 'applierKey', 32))->setDescription('Internal field.'),
            (new StringField('usage_key', 'usageKey', 32))->setDescription('Internal field.'),
            (new StringField('picker_key', 'pickerKey', 32))->setDescription('Internal field.'),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id'),
            (new ManyToManyAssociationField('discountRules', RuleDefinition::class, PromotionDiscountRuleDefinition::class, 'discount_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionDiscountPrices', PromotionDiscountPriceDefinition::class, 'discount_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
