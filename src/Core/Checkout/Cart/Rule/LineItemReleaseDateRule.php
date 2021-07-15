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

class LineItemReleaseDateRule extends Rule
{
    protected ?string $lineItemReleaseDate;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lineItemReleaseDate = null)
    {
        parent::__construct();

        $this->lineItemReleaseDate = $lineItemReleaseDate;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemReleaseDate';
    }

    public function getConstraints(): array
    {
        $constraints = [
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
                        self::OPERATOR_EMPTY,
                    ]
                ),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['lineItemReleaseDate'] = [new NotBlank(), new Type('string')];

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        try {
            $ruleValue = $this->buildDate($this->lineItemReleaseDate);
        } catch (\Exception $e) {
            return false;
        }

        if ($scope instanceof LineItemScope) {
            return $this->matchesReleaseDate($scope->getLineItem(), $ruleValue);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesReleaseDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesReleaseDate(LineItem $lineItem, ?\DateTime $ruleValue): bool
    {
        try {
            $releasedAtString = $lineItem->getPayloadValue('releaseDate');

            if ($releasedAtString === null) {
                return $this->operator === self::OPERATOR_EMPTY;
            }

            /** @var \DateTime $itemReleased */
            $itemReleased = $this->buildDate($releasedAtString);
        } catch (\Exception $e) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $ruleValue && $itemReleased->format('Y-m-d H:i:s') === $ruleValue->format('Y-m-d H:i:s');

            case self::OPERATOR_NEQ:
                // due to the cs fixer that always adds ===
                // its necessary to use the string when comparing, otherwise its never working
                return $ruleValue && $itemReleased->format('Y-m-d H:i:s') !== $ruleValue->format('Y-m-d H:i:s');

            case self::OPERATOR_GT:
                return $ruleValue && $itemReleased > $ruleValue;

            case self::OPERATOR_LT:
                return $ruleValue && $itemReleased < $ruleValue;

            case self::OPERATOR_GTE:
                return $ruleValue && $itemReleased >= $ruleValue;

            case self::OPERATOR_LTE:
                return $ruleValue && $itemReleased <= $ruleValue;

            case self::OPERATOR_EMPTY:
                return false;

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    /**
     * @throws \Exception
     */
    private function buildDate(?string $dateString): ?\DateTime
    {
        if ($dateString === null) {
            return null;
        }

        $dateTime = new \DateTime($dateString);

        return $dateTime;
    }
}
