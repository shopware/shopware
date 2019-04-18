<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class CartHasDeliveryFreeItemRule extends Rule
{
    /**
     * @var bool
     */
    protected $allowed;

    public function __construct()
    {
        $this->allowed = true;
    }

    public function getName(): string
    {
        return 'cartHasDeliveryFreeItem';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        /** @var LineItem $lineItem */
        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if (!$lineItem->getDeliveryInformation()) {
                continue;
            }
            if ($lineItem->getDeliveryInformation()->getFreeDelivery() === $this->allowed) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'allowed' => [new Type('bool')],
        ];
    }
}
