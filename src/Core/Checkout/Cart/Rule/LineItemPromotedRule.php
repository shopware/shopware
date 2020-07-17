<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class LineItemPromotedRule extends Rule
{
    /**
     * @var bool
     */
    protected $isPromoted;

    public function __construct(bool $isPromoted = false)
    {
        parent::__construct();

        $this->isPromoted = $isPromoted;
    }

    public function getName(): string
    {
        return 'cartLineItemPromoted';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->isItemMatching($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->isItemMatching($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'isPromoted' => [new Type('bool')],
        ];
    }

    private function isItemMatching(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('markAsTopseller') === $this->isPromoted;
    }
}
