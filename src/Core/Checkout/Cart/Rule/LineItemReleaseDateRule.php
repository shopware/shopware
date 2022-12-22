<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemReleaseDateRule extends Rule
{
    protected ?string $lineItemReleaseDate;

    protected string $operator;

    /**
     * @internal
     */
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
            'operator' => RuleConstraints::datetimeOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['lineItemReleaseDate'] = RuleConstraints::datetime();

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

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->dateTimeField('lineItemReleaseDate');
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesReleaseDate(LineItem $lineItem, ?\DateTime $ruleValue): bool
    {
        try {
            $releasedAtString = $lineItem->getPayloadValue('releaseDate');

            if ($releasedAtString === null) {
                if (!Feature::isActive('v6.5.0.0')) {
                    return $this->operator === self::OPERATOR_EMPTY;
                }

                return RuleComparison::isNegativeOperator($this->operator);
            }

            /** @var \DateTime $itemReleased */
            $itemReleased = $this->buildDate($releasedAtString);
        } catch (\Exception $e) {
            return false;
        }

        if ($ruleValue === null) {
            return false;
        }

        return RuleComparison::datetime($itemReleased, $ruleValue, $this->operator);
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
