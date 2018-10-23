<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule\Definition;

use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\StringFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class BillingStreetRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Billing street',
            BillingStreetRule::class,
            [
                new Scope(Scope::IDENTIFIER_CHECKOUT),
            ],
            [
                new Field(
                    'streetName',
                    true,
                    new StringFieldType(new FieldOperator(FieldOperator::IDENTIFIER_EQUALS))
                ),
            ]
        );
    }
}