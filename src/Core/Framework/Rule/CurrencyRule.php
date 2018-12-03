<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\NotBlank;

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

    public static function getConstraints(): array
    {
        return [
            'currencyIds' => [new NotBlank(), new ArrayOfUuid()],
        ];
    }
}
