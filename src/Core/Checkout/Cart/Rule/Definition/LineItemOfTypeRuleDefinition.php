<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule\Definition;

use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\SelectValueFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class LineItemOfTypeRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Lineitem of type',
            LineItemOfTypeRule::class,
            [
                new Scope(Scope::IDENTIFIER_LINEITEM),
            ],
            [
                new Field(
                    'type',
                    true,
                    new SelectValueFieldType(
                        [
                            ProductCollector::LINE_ITEM_TYPE,
                        ],
                        SelectValueFieldType::IDENTIFIER_SINGLESELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
            ]
        );
    }
}