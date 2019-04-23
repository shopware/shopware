<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class PromotionPersonaRuleDefinition extends MappingEntityDefinition
{
    /**
     * This class is used as m:n relation between promotions and persona rules.
     * It gives the option to assign what rules may use this promotion.
     */
    public static function getEntityName(): string
    {
        return 'promotion_persona_rule';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            (new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id'))->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
