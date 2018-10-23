<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule\Definition;

use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Framework\Rule\Definition\RuleDefinition;
use Shopware\Core\Framework\Rule\Type\Field\Endpoint;
use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Rule\Type\Field\FieldOperator;
use Shopware\Core\Framework\Rule\Type\Field\SelectFieldType;
use Shopware\Core\Framework\Rule\Type\RuleTypeStruct;
use Shopware\Core\Framework\Rule\Type\Scope;
use Shopware\Core\System\Country\CountryDefinition;

class BillingCountryRuleDefinition implements RuleDefinition
{
    public function getTypeStruct(): RuleTypeStruct
    {
        return new RuleTypeStruct(
            'Billing country',
            BillingCountryRule::class,
            [
                new Scope(Scope::IDENTIFIER_CHECKOUT),
            ],
            [
                new Field(
                    'countryIds',
                    true,
                    new SelectFieldType(
                        new Endpoint('/api/v1/' . CountryDefinition::getEntityName(), 'name', 'id'),
                        SelectFieldType::IDENTIFIER_MULTISELECT,
                        new FieldOperator(FieldOperator::IDENTIFIER_IS_ONE_OF),
                        new FieldOperator(FieldOperator::IDENTIFIER_EQUALS)
                    )
                ),
            ]
        );
    }
}