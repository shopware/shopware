<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule\Definition;

use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Endpoint;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\IntFieldType;
use Shopware\Core\Framework\Rule\Type\Field\SelectFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class LineItemWithQuantityRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Lineitem with quantity',
            LineItemWithQuantityRule::class,
            [
                new Scope(Scope::IDENTIFIER_LINEITEM),
            ],
            [
                new Field(
                    'id',
                    true,
                    new SelectFieldType(
                        new Endpoint('/api/v1/' . ProductDefinition::getEntityName(), 'name', 'id'),
                        SelectFieldType::IDENTIFIER_SINGLESELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
                new Field(
                    'quantity',
                    false,
                    new IntFieldType(
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_NOT_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_LOWER_THAN_EQUALS),
                        new FieldOperator(FieldOperator::IDENTIFIER_GREATER_THAN_EQUALS)
                    )
                ),
            ]
        );
    }
}