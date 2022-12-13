<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\CartException;
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
    public const RULE_NAME = 'cartLineItemClearanceSale';

    protected bool $clearanceSale;

    /**
     * @internal
     */
    public function __construct(bool $clearanceSale = false)
    {
        parent::__construct();

        $this->clearanceSale = $clearanceSale;
    }

    /**
     * @throws CartException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesClearanceSaleCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
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
     * @throws CartException
     */
    private function matchesClearanceSaleCondition(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('isCloseout') === $this->clearanceSale;
    }
}
