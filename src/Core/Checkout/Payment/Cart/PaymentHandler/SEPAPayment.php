<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionRepository;
use Shopware\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Defaults;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class SEPAPayment implements PaymentHandlerInterface
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionRepository
     */
    private $transactionRepository;

    public function __construct(OrderTransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
    {
        $data = [
            'id' => $transaction->getTransactionId(),
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
        ];
        $this->transactionRepository->update([$data], $context);

        return null;
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
    }
}
