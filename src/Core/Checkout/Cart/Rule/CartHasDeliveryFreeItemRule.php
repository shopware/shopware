<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CartHasDeliveryFreeItemRule extends Rule
{
    protected bool $allowed;

    /**
     * @internal
     */
    public function __construct(bool $allowed = true)
    {
        parent::__construct();

        $this->allowed = $allowed;
    }

    public function getName(): string
    {
        return 'cartHasDeliveryFreeItem';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->isFreeDeliveryItem($scope->getLineItem()) === $this->allowed;
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $hasFreeDeliveryItems = $this->hasFreeDeliveryItems($scope->getCart()->getLineItems());

        return $hasFreeDeliveryItems === $this->allowed;
    }

    public function getConstraints(): array
    {
        return [
            'allowed' => RuleConstraints::bool(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('allowed');
    }

    private function hasFreeDeliveryItems(LineItemCollection $lineItems): bool
    {
        foreach ($lineItems->getFlat() as $lineItem) {
            if ($this->isFreeDeliveryItem($lineItem) === true) {
                return true;
            }
        }

        return false;
    }

    private function isFreeDeliveryItem(LineItem $lineItem): bool
    {
        $deliveryInformation = $lineItem->getDeliveryInformation();
        if ($deliveryInformation === null) {
            return false;
        }

        return $deliveryInformation->getFreeDelivery();
    }
}
