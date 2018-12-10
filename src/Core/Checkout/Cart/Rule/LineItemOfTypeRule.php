<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemOfTypeRule extends Rule
{
    /**
     * @var string
     */
    protected $lineItemType;

    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof LineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. LineItemScope expected']
            );
        }

        return new Match(
            $scope->getLineItem()->getType() === $this->lineItemType,
            ['LineItem type does not match']
        );
    }

    public static function getConstraints(): array
    {
        return [
            'lineItemType' => [new NotBlank(), new Type('string')],
        ];
    }
}
