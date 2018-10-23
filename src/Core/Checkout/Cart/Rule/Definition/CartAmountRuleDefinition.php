<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule\Definition;

use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\IntFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class CartAmountRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Cart amount',
            CartAmountRule::class,
            [
                new Scope(Scope::IDENTIFIER_CART),
            ],
            [
                new Field(
                    'amount',
                    true,
                    new IntFieldType(
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_NOT_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_GREATER_THAN_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_LOWER_THAN_EQUALS)
                    )
                )
            ]
        );
    }
}