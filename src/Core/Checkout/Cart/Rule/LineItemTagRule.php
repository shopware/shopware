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
    /**
     * @var string
     */
    protected $operator;

    /**
     * @var string[]
     */
    protected $identifiers;

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

        foreach ($scope->getCart()->getLineItems() as $lineItem) {
            $identifiers = array_merge($identifiers, $this->extractTagIds($lineItem));
        }

        return $this->tagsMatches($identifiers);
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $identifiers = $this->extractTagIds($lineItem);

        return $this->tagsMatches($identifiers);
    }

    private function tagsMatches(array $tags): bool
    {
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return !empty(array_intersect($tags, $this->identifiers));
            case self::OPERATOR_NEQ:
                return empty(array_intersect($tags, $this->identifiers));
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
