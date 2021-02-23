<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemPropertyRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $identifiers;

    protected string $operator;

    public function __construct(array $identifiers = [], string $operator = self::OPERATOR_EQ)
    {
        parent::__construct();
        $this->identifiers = $identifiers;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemProperty';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatch($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->lineItemMatch($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    private function lineItemMatch(LineItem $lineItem): bool
    {
        $properties = $lineItem->getPayloadValue('propertyIds') ?? [];
        $options = $lineItem->getPayloadValue('optionIds') ?? [];

        $ids = array_merge($properties, $options);

        $diff = array_intersect($ids, $this->identifiers);

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty($diff);
            case self::OPERATOR_NEQ:
                return empty($diff);
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
