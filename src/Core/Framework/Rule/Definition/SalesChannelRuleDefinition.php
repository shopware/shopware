<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Definition;

use Shopware\Core\Framework\Rule\SalesChannelRule;
use Shopware\Core\Framework\Rule\Type\Field\Endpoint;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\SelectFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class SalesChannelRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Sales channel',
            SalesChannelRule::class,
            [
                new Scope(Scope::IDENTIFIER_GLOBAL),
            ],
            [
                new Field(
                    'salesChannelIds', true, new SelectFieldType(
                        new Endpoint('/api/v1/sales-channel', 'name', 'id'), SelectFieldType::IDENTIFIER_MULTISELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF),
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
            ]
        );
    }
}