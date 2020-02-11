<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemTopsellerRule extends Rule
{
    /**
     * @var bool
     */
    protected $isTopseller;

    public function __construct(bool $isTopseller = false)
    {
        parent::__construct();

        $this->isTopseller = $isTopseller;
    }

    public function getName(): string
    {
        return 'cartLineItemTopseller';
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesTopsellerCondition($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchesTopsellerCondition($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'isTopseller' => [new NotBlank(), new Type('bool')],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchesTopsellerCondition(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('markAsTopseller') === $this->isTopseller;
    }
}
