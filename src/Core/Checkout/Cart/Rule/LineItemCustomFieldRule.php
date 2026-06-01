<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class LineItemCustomFieldRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemCustomField';

    /**
     * @var array<string|int|bool|float>|string|int|float|bool|null
     */
    protected array|string|int|float|bool|null $renderedFieldValue = null;

    protected ?string $selectedField = null;

    protected ?string $selectedFieldSet = null;

    /**
     * @param array<string, mixed> $renderedField
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $renderedField = []
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->isCustomFieldValid($scope->getLineItem(), $scope->getSalesChannelContext());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->isCustomFieldValid($lineItem, $scope->getSalesChannelContext())) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }

    private function isCustomFieldValid(LineItem $lineItem, SalesChannelContext $context): bool
    {
        $customFields = $lineItem->getPayloadValue('customFields');
        if ($customFields === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return CustomFieldRule::match(
            $this->renderedField,
            $this->renderedFieldValue,
            $this->operator,
            $customFields,
            $context,
        );
    }
}
