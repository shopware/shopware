<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class PromotionLineItemRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected ?array $identifiers;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?array $identifiers = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->identifiers = $identifiers;
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $promotionLineItems = $scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $hasPromotionLineItems = \count($promotionLineItems) === 0;

        if ($this->operator === self::OPERATOR_EQ && $hasPromotionLineItems) {
            return false;
        }

        if ($this->operator === self::OPERATOR_NEQ && $hasPromotionLineItems) {
            return true;
        }

        foreach ($scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE) as $lineItem) {
            if ($lineItem->getPayloadValue('promotionId') === null) {
                continue;
            }

            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]|null
     */
    public function getIdentifiers(): ?array
    {
        return $this->identifiers;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'promotionLineItem';
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        if ($this->identifiers === null) {
            return false;
        }

        $promotionId = $lineItem->getPayloadValue('promotionId');
        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($promotionId, $this->identifiers, true);

            case self::OPERATOR_NEQ:
                return !\in_array($promotionId, $this->identifiers, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }
}
