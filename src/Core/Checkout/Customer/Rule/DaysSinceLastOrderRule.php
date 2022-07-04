<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class DaysSinceLastOrderRule extends Rule
{
    /**
     * @var string
     */
    protected $operator;

    /**
     * @var int
     */
    protected $daysPassed;

    /**
     * @var \DateTime|null
     */
    private $dateTime;

    /**
     * @internal
     */
    public function __construct(?\DateTimeInterface $dateTime = null)
    {
        parent::__construct();

        if ($dateTime) {
            $this->dateTime = (new \DateTime())->setTimestamp($dateTime->getTimestamp());
        }
    }

    public function getName(): string
    {
        return 'customerDaysSinceLastOrder';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $currentDate = $this->dateTime ?? new \DateTime();
        $customer = $scope->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return false;
        }

        $lastOrderDate = $customer->getLastOrderDate();

        if ($lastOrderDate === null) {
            return $this->operator === self::OPERATOR_EMPTY;
        }

        $interval = $lastOrderDate->diff($currentDate);

        /*
         * checking if the interval should be increased since it's a higher day than he might expects
         *
         * example:
         * you ordered at 10pm and want to order something the next day at 8am. So this should count as 1 passed day
         * but PHP would not handle this as a day
         */
        if (
                // checking if lastOrderDate is in the past
                $currentDate > $lastOrderDate
                && (
                    // checking if the current time is smaller than the one of the last order
                    (int) $currentDate->format('H') < (int) $lastOrderDate->format('H')
                    || (
                        (int) $currentDate->format('H') === (int) $lastOrderDate->format('H')
                        && (int) $currentDate->format('i') < (int) $lastOrderDate->format('i')
                    )
                )
        ) {
            $interval = $lastOrderDate->diff($currentDate->modify('+1 day'));
        }

        if ($this->operator === self::OPERATOR_EMPTY) {
            return false;
        }

        return RuleComparison::numeric((int) $interval->days, $this->daysPassed, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['daysPassed'] = RuleConstraints::int();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->intField('daysPassed');
    }
}
