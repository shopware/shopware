<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemsInCartRule extends Rule
{
    /**
     * @var string[]
     */
    protected $identifiers;

    public function match(
        RuleScope $scope
    ): Match {
        if (!$scope instanceof CartRuleScope) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleScope expected']
            );
        }

        $elements = $scope->getCart()->getLineItems()->getFlat();
        $identifiers = array_map(function (LineItem $element) {
            return $element->getKey();
        }, $elements);

        return new Match(
            !empty(array_intersect($identifiers, $this->identifiers)),
            ['Line items not in cart']
        );
    }

    public static function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
        ];
    }
}
