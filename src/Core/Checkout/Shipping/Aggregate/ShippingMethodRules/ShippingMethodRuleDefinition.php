<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodRules;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ShippingMethodRuleDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'shipping_method_rule';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, true),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, true),
        ]);
    }
}
