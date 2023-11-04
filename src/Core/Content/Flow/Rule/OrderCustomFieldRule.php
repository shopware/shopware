<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\CustomFieldRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class OrderCustomFieldRule extends FlowRule
{
    final public const RULE_NAME = 'orderCustomField';

    protected string|int|bool|null|float $renderedFieldValue = null;

    /**
     * @param array<string, string> $renderedField
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $renderedField = []
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        $orderCustomFields = $scope->getOrder()->getCustomFields() ?? [];

        return CustomFieldRule::match($this->renderedField, $this->renderedFieldValue, $this->operator, $orderCustomFields);
    }

    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }
}
