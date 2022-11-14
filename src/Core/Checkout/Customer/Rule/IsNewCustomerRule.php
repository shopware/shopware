<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class IsNewCustomerRule extends Rule
{
    /**
     * @var bool
     */
    protected $isNew;

    /**
     * @internal
     */
    public function __construct(bool $isNew = true)
    {
        parent::__construct();
        $this->isNew = $isNew;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        if (!$customer->getFirstLogin()) {
            return false;
        }

        if ($this->isNew) {
            return $customer->getFirstLogin()->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
        }

        return $customer->getFirstLogin()->format('Y-m-d') !== (new \DateTime())->format('Y-m-d');
    }

    public function getConstraints(): array
    {
        return [
            'isNew' => RuleConstraints::bool(true),
        ];
    }

    public function getName(): string
    {
        return 'customerIsNewCustomer';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isNew');
    }
}
