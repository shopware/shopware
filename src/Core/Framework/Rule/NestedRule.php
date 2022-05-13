<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class NestedRule extends Rule
{
    public const NAME = 'nestedRule';

    protected string $operator = self::OPERATOR_EQ;

    protected ?string $ruleId = null;

    protected ?Rule $rule = null;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $ruleId = null, ?Rule $rule = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->ruleId = $ruleId;
        $this->rule = $rule;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$this->rule) {
            return $this->operator !== self::OPERATOR_NEQ;
        }
        $nestedResult = $this->rule->match($scope);

        return $this->operator === self::OPERATOR_EQ ? $nestedResult : !$nestedResult;
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new NotBlank(),
                new Choice([
                    self::OPERATOR_EQ,
                    self::OPERATOR_NEQ,
                ]),
            ],
            'ruleId' => [new NotBlank(), new Uuid()],
        ];
    }
}
