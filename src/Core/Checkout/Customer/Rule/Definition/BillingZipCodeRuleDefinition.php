<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule\Definition;

use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\StringFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class BillingZipCodeRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Billing zip code',
            BillingZipCodeRule::class,
            [
                new Scope(Scope::IDENTIFIER_CHECKOUT),
            ],
            [
                new Field(
                    'zipCodes',
                    true,
                    new StringFieldType(
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF),
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
            ]
        );
    }
}