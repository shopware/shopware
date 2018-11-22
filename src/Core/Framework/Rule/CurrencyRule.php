<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

class CurrencyRule extends Rule
{
    /**
     * @var string[]
     */
    protected $currencyIds;

    public function match(RuleScope $scope): Match
    {
        return new Match(
            \in_array($scope->getContext()->getCurrencyId(), $this->currencyIds, true),
            ['Currency not matched']
        );
    }
}
