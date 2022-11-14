<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemClearanceSaleRule extends Rule
{
    protected bool $clearanceSale;

    /**
     * @internal
     */
    public function __construct(bool $clearanceSale = false)
    {
        parent::__construct();

        $this->clearanceSale = $clearanceSale;
    }

    public function getName(): string
    {
        return 'cartLineItemClearanceSale';
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesClearanceSaleCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesClearanceSaleCondition($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'clearanceSale' => RuleConstraints::bool(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('clearanceSale');
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesClearanceSaleCondition(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('isCloseout') === $this->clearanceSale;
    }
}
