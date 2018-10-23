<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Definition;

use Shopware\Core\Framework\Rule\CurrencyRule;
use Shopware\Core\Framework\Rule\Type\Field\Endpoint;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\SelectFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;
use Shopware\Core\System\Currency\CurrencyDefinition;

class CurrencyRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Currency Rule',
            CurrencyRule::class,
            [
                new Scope(Scope::IDENTIFIER_GLOBAL)
            ],
            [
                new Field(
                    'currencyIds',
                    true,
                    new SelectFieldType(
                        new Endpoint('/api/v1/' . CurrencyDefinition::getEntityName(), 'attributes.name', 'id'),
                        SelectFieldType::IDENTIFIER_MULTISELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF),
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
            ]
        );
    }
}