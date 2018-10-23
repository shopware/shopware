<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Definition;

use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\Type\Field\BoolFieldType;
use Shopware\Core\Framework\Rule\Type\Field\DateFieldType;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;

class DateRangeRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Daterange rule',
            DateRangeRule::class,
            [
                new Scope(Scope::IDENTIFIER_GLOBAL),
            ],
            [
                new Field(
                    'fromDate',
                    true,
                    new DateFieldType()
                ),
                new Field(
                    'toDate',
                    true,
                    new DateFieldType()
                ),
                new Field(
                    'useTime',
                    false,
                    new BoolFieldType()
                ),
            ]
        );
    }
}