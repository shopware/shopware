<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule\Specification\CalculatedLineItem;

use Shopware\Core\Checkout\Rule\Specification\Match;
use Shopware\Core\Checkout\Rule\Specification\Rule;
use Shopware\Core\Checkout\Rule\Specification\Scope\CalculatedLineItemScope;
use Shopware\Core\Checkout\Rule\Specification\Scope\RuleScope;

class LineItemOfTypeRule extends Rule
{
    /**
     * @var string
     */
    protected $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof CalculatedLineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemScope expected']
            );
        }

        return new Match(
            $scope->getCalculatedLineItem()->getType() == $this->type,
            ['LineItem type does not match']
        );
    }
}
