<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ComputedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\SourceContext;

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
            (new ComputedField('payload', 'payload'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discountSurcharges', DiscountSurchargeDefinition::class, 'rule_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productPriceRules', ProductPriceRuleDefinition::class, 'rule_id', false, 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
