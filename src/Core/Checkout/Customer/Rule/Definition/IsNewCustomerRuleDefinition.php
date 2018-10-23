<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule\Definition;

use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class IsNewCustomerRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'is new customer',
            IsNewCustomerRule::class,
            [
                new Scope(Scope::IDENTIFIER_CHECKOUT),
            ],
            []
        );
    }
}