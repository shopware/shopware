<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

#[Package('business-ops')]
class OrderTransactionStatusRule extends FlowRule
{
    public const RULE_NAME = 'orderTransactionStatus';

    /**
     * @internal
     *
     * @param list<string> $stateIds
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $stateIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'stateIds' => RuleConstraints::uuids(),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope || $this->stateIds === null) {
            return false;
        }

        if (!$transactions = $scope->getOrder()->getTransactions()) {
            return false;
        }

        $paymentMethodId = $transactions->last()->getStateId();

        foreach ($transactions->getElements() as $transaction) {
            $technicalName = $transaction->getStateMachineState()?->getTechnicalName();
            if ($technicalName !== null
                && $technicalName !== OrderTransactionStates::STATE_FAILED
                && $technicalName !== OrderTransactionStates::STATE_CANCELLED
            ) {
                $paymentMethodId = $transaction->getStateId();

                break;
            }
        }

        return RuleComparison::stringArray($paymentMethodId, $this->stateIds, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField(
                'stateIds',
                StateMachineStateDefinition::ENTITY_NAME,
                true,
                [
                    'criteria' => [
                        'associations' => [
                            'stateMachine',
                        ],
                        'filters' => [
                            [
                                'type' => 'equals',
                                'field' => 'state_machine_state.stateMachine.technicalName',
                                'value' => 'order_transaction.state',
                            ],
                        ],
                    ],
                ]
            );
    }
}
