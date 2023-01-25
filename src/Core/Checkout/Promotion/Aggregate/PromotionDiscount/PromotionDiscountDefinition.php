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

    protected function getParentDefinitionClass(): ?string
    {
        return PromotionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class, 'id'))->addFlags(new Required()),
            (new StringField('scope', 'scope'))->addFlags(new Required()),
            (new StringField('type', 'type', 32))->addFlags(new Required()),
            (new FloatField('value', 'value'))->addFlags(new Required()),
            (new BoolField('consider_advanced_rules', 'considerAdvancedRules'))->addFlags(new Required()),
            new FloatField('max_value', 'maxValue'),

            (new StringField('sorter_key', 'sorterKey', 32)),
            (new StringField('applier_key', 'applierKey', 32)),
            (new StringField('usage_key', 'usageKey', 32)),
            (new StringField('picker_key', 'pickerKey', 32)),
            (new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id'))->addFlags(),
            (new ManyToManyAssociationField('discountRules', RuleDefinition::class, PromotionDiscountRuleDefinition::class, 'discount_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionDiscountPrices', PromotionDiscountPriceDefinition::class, 'discount_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
