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

class LineItemCreationDateRule extends Rule
{
    protected ?string $lineItemCreationDate;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?string $lineItemCreationDate = null)
    {
        parent::__construct();

        $this->lineItemCreationDate = $lineItemCreationDate;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemCreationDate';
    }

    public function getConstraints(): array
    {
        return [
            'lineItemCreationDate' => RuleConstraints::datetime(),
            'operator' => RuleConstraints::datetimeOperators(false),
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

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesCreationDate($lineItem, $ruleValue)) {
                return true;
            }
        }

        return false;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->dateTimeField('lineItemCreationDate');
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
                if (!Feature::isActive('v6.5.0.0')) {
                    return false;
                }

                return RuleComparison::isNegativeOperator($this->operator);
            }

            $itemCreated = $this->buildDate($itemCreatedString);
        } catch (\Exception $e) {
            return false;
        }

        return RuleComparison::datetime($itemCreated, $ruleValue, $this->operator);
    }

    /**
     * @throws \Exception
     */
    private function buildDate(string $dateString): \DateTime
    {
        $dateTime = new \DateTime($dateString);

        return $dateTime;
    }
}
