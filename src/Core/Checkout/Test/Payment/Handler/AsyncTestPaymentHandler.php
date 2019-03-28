<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AsyncTestPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    public const REDIRECT_URL = 'https://shopware.com';

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $transactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(AsyncPaymentTransactionStruct $transaction, Context $context): RedirectResponse
    {
        return new RedirectResponse(self::REDIRECT_URL);
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, Context $context): void
    {
        if ($request->query->getBoolean('cancel')) {
            throw new CustomerCanceledAsyncPaymentException(
                $transaction->getOrderTransaction()->getId(),
                'Async Test Payment canceled'
            );
        }

        $completeStateId = $this->stateMachineRegistry->getStateByTechnicalName(
            Defaults::ORDER_TRANSACTION_STATE_MACHINE,
            Defaults::ORDER_TRANSACTION_STATES_PAID,
            $context
        )->getId();

        $transactionData = [
            'id' => $transaction->getOrderTransaction()->getId(),
            'stateId' => $completeStateId,
        ];

        $this->transactionRepository->update([$transactionData], $context);
    }
}
