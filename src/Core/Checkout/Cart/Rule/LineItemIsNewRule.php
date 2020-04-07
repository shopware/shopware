<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class LineItemIsNewRule extends Rule
{
    /**
     * @var bool
     */
    protected $isNew;

    public function __construct(bool $isNew = false)
    {
        parent::__construct();

        $this->isNew = $isNew;
    }

    public function getName(): string
    {
        return 'cartLineItemIsNew';
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchLineItemIsNew($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->matchLineItemIsNew($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'isNew' => [new Type('bool')],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function matchLineItemIsNew(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('isNew') === $this->isNew;
    }
}
