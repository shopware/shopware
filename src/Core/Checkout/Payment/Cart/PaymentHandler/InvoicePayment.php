<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class InvoicePayment implements PaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(EntityRepositoryInterface $transactionRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->transactionRepository = $transactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
    {
        $completeStateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();

        $data = [
            'id' => $transaction->getTransactionId(),
            'stateId' => $completeStateId,
        ];

        $this->transactionRepository->update([$data], $context);

        return null;
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
    }
}
