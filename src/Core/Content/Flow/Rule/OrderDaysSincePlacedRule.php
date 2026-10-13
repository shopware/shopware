<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Clock\Clock;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class OrderDaysSincePlacedRule extends FlowRule
{
    final public const RULE_NAME = 'orderDaysSincePlaced';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $daysPassed = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope || $this->daysPassed === null) {
            return false;
        }

        $currentDate = $scope->getCurrentTime()->setTime(0, 0, 0, 0);

        $orderDate = \DateTime::createFromImmutable(Clock::get()->now())
            ->setTimestamp($scope->getOrder()->getOrderDate()->getTimestamp())
            ->setTime(0, 0);

        return RuleComparison::numeric((int) $orderDate->diff($currentDate)->days, $this->daysPassed, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'daysPassed' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('daysPassed', ['unit' => RuleConfig::UNIT_TIME]);
    }
}
