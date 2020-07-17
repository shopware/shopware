<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemCreationDateRule extends Rule
{
    /**
     * @var string|null
     */
    protected $lineItemCreationDate;

    /**
     * @var string
     */
    protected $operator;

    public function getName(): string
    {
        return 'cartLineItemCreationDate';
    }

    public function getConstraints(): array
    {
        return [
            'lineItemCreationDate' => [new NotBlank(), new Type('string')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if ($this->lineItemCreationDate === null) {
            return false;
        }

        try {
            $ruleValue = $this->buildDate($this->lineItemCreationDate);
        } catch (\Exception $e) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesCreationDate($scope->getLineItem(), $ruleValue);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchesCreationDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesCreationDate(LineItem $lineItem, \DateTime $ruleValue): bool
    {
        try {
            /** @var string|null $itemCreatedString */
            $itemCreatedString = $lineItem->getPayloadValue('createdAt');

            if ($itemCreatedString === null) {
                return false;
            }

            $itemCreated = $this->buildDate($itemCreatedString);
        } catch (\Exception $e) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $itemCreated->format('Y-m-d H:i:s') === $ruleValue->format('Y-m-d H:i:s');

            case self::OPERATOR_NEQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $itemCreated->format('Y-m-d H:i:s') !== $ruleValue->format('Y-m-d H:i:s');

            case self::OPERATOR_GT:
                return $itemCreated > $ruleValue;

            case self::OPERATOR_LT:
                return $itemCreated < $ruleValue;

            case self::OPERATOR_GTE:
                return $itemCreated >= $ruleValue;

            case self::OPERATOR_LTE:
                return $itemCreated <= $ruleValue;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    /**
     * @throws \Exception
     */
    private function buildDate(string $dateString): \DateTime
    {
        $dateTime = new \DateTime($dateString);
        $dateTime->setTime(0, 0, 0);

        return $dateTime;
    }
}
