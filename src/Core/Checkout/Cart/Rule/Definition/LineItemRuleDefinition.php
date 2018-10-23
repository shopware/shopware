<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule\Definition;

use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\StringFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class LineItemRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Lineitem',
            LineItemRule::class,
            [
                new Scope(Scope::IDENTIFIER_LINEITEM),
            ],
            [
                new Field(
                    'identifiers',
                    true,
                    new StringFieldType(
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF)
                    )
                ),
            ]
        );
    }
}