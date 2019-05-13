<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class CartHasDeliveryFreeItemRule extends Rule
{
    /**
     * @var bool
     */
    protected $allowed;

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
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $hasFreeDeliveryItems = $this->hasFreeDeliveryItems($scope->getCart()->getLineItems());

        return $hasFreeDeliveryItems === $this->allowed;
    }

    public function getConstraints(): array
    {
        return [
            'allowed' => [new Type('bool')],
        ];
    }

    private function hasFreeDeliveryItems(LineItemCollection $lineItems): bool
    {
        foreach ($lineItems->getIterator() as $lineItem) {
            if (!$lineItem->getDeliveryInformation()) {
                continue;
            }
            if ($lineItem->getDeliveryInformation()->getFreeDelivery()) {
                return true;
            }
        }

        return false;
    }
}
