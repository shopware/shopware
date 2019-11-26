<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class CurrencyRule extends Rule
{
    /**
     * @var string[]
     */
    protected $currencyIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $currencyIds = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->currencyIds = $currencyIds;
    }

    public function match(RuleScope $scope): bool
    {
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($scope->getContext()->getCurrencyId(), $this->currencyIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($scope->getContext()->getCurrencyId(), $this->currencyIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'currencyIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'currency';
    }
}
