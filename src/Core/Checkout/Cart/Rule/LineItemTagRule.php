<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemTagRule extends Rule
{
    /**
     * @var string
     */
    protected $operator;

    /**
     * @var string[]
     */
    protected $identifiers;

    public function __construct()
    {
        $this->operator = self::OPERATOR_EQ;
    }

    public function getName(): string
    {
        return 'cartLineItemTag';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $elements = $scope->getCart()->getLineItems();
        $identifiers = $this->extractTagIds($elements);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty(array_intersect($identifiers, $this->identifiers));
            case self::OPERATOR_NEQ:
                return empty(array_intersect($identifiers, $this->identifiers));

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    private function extractTagIds(LineItemCollection $lineItems): array
    {
        $identifiers = [];

        foreach ($lineItems as $lineItem) {
            if (!$lineItem->hasPayloadValue('tags')) {
                continue;
            }

            array_push($identifiers, ...$lineItem->getPayload()['tags']);
        }

        return $identifiers;
    }
}
