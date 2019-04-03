<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodRules;

use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class PaymentMethodRuleDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'payment_method_rule';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', true),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', true),
        ]);
    }
}
