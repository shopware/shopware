<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class CampaignCodeOfOrderRule extends Rule
{
    final public const RULE_NAME = 'orderCampaignCode';

    /**
     * @internal
     *
     * @param array<string> $campaignCode
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $campaignCode = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        if (!$this->campaignCode && $this->operator !== self::OPERATOR_EMPTY) {
            throw CartException::unsupportedValue(\gettype($this->campaignCode), self::class);
        }
        if (!$campaignCode = $scope->getOrder()->getCampaignCode()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::stringArray(
            $campaignCode,
            $this->campaignCode !== null ? array_values(
                array_map('mb_strtolower', $this->campaignCode)
            ) : [],
            $this->operator,
        );
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['campaignCode'] = RuleConstraints::stringArray();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->taggedField('campaignCode');
    }
}
