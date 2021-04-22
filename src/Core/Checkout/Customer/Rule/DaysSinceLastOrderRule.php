<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

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

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return $interval->days === $this->daysPassed;
            case self::OPERATOR_NEQ:
                return $interval->days !== $this->daysPassed;
            case self::OPERATOR_LT:
                return $interval->days < $this->daysPassed;
            case self::OPERATOR_LTE:
                return $interval->days <= $this->daysPassed;
            case self::OPERATOR_GT:
                return $interval->days > $this->daysPassed;
            case self::OPERATOR_GTE:
                return $interval->days >= $this->daysPassed;
            case self::OPERATOR_EMPTY:
                return false;
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_EQ,
                        self::OPERATOR_LTE,
                        self::OPERATOR_GTE,
                        self::OPERATOR_NEQ,
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

        $constraints['daysPassed'] = [new NotBlank(), new Type('int')];

        return $constraints;
    }
}
