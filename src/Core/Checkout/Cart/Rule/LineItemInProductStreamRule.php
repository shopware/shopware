<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemInProductStreamRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $streamIds;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, array $streamIds = [])
    {
        parent::__construct();

        $this->streamIds = $streamIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemInProductStream';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfProductStream($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfProductStream($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ, self::OPERATOR_EMPTY]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['streamIds'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfProductStream(LineItem $lineItem): bool
    {
        $streamIds = (array) $lineItem->getPayloadValue('streamIds');

        $matches = array_intersect($streamIds, $this->streamIds);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty($matches);

            case self::OPERATOR_NEQ:
                return empty($matches);

            case self::OPERATOR_EMPTY:
                return empty($streamIds);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
