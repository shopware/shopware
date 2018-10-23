<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule\Definition;

use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Endpoint;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\SelectFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class LineItemsInCartRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Lineitems in cart',
            LineItemsInCartRule::class,
            [
                new Scope(Scope::IDENTIFIER_CART),
            ],
            [
                new Field(
                    'identifiers',
                    true,
                    new SelectFieldType(
                        new Endpoint('/api/v1/' . ProductDefinition::getEntityName(), 'name', 'id'),
                        SelectFieldType::IDENTIFIER_MULTISELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF)
                    )
                ),
            ]
        );
    }
}