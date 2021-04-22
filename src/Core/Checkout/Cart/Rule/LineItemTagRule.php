<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemTagRule extends Rule
{
    protected string $operator;

    /**
     * @var string[]|null
     */
    protected ?array $identifiers;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function getName(): string
    {
        return 'cartLineItemTag';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $identifiers = [];

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            $identifiers = array_merge($identifiers, $this->extractTagIds($lineItem));
        }

        return $this->tagsMatches($identifiers);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
                new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ, self::OPERATOR_EMPTY]),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['identifiers'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $identifiers = $this->extractTagIds($lineItem);

        return $this->tagsMatches($identifiers);
    }

    private function tagsMatches(array $tags): bool
    {
        if ($this->identifiers === null && $this->operator !== self::OPERATOR_EMPTY) {
            return false;
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return $this->identifiers && !empty(array_intersect($tags, $this->identifiers));
            case self::OPERATOR_NEQ:
                return $this->identifiers && empty(array_intersect($tags, $this->identifiers));
            case self::OPERATOR_EMPTY:
                return empty($tags);
            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    private function extractTagIds(LineItem $lineItem): array
    {
        if (!$lineItem->hasPayloadValue('tagIds')) {
            return [];
        }

        return $lineItem->getPayload()['tagIds'];
    }
}
