<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class LineItemNoveltyRule extends Rule
{
    /**
     * @var bool
     */
    protected $isNovelty;

    public function __construct(bool $isNovelty = false)
    {
        parent::__construct();

        $this->isNovelty = $isNovelty;
    }

    public function getName(): string
    {
        return 'cartLineItemNovelty';
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->isNoveltyItem($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            if ($this->isNoveltyItem($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'isNovelty' => [new Type('bool')],
        ];
    }

    /**
     * @throws PayloadKeyNotFoundException
     */
    private function isNoveltyItem(LineItem $lineItem): bool
    {
        return (bool) $lineItem->getPayloadValue('isNew') === $this->isNovelty;
    }
}
