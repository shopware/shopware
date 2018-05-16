<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionRepository;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Checkout\Payment\Cart\PaymentTransactionStruct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class InvoicePayment implements PaymentHandlerInterface
{
    /**
     * @var OrderTransactionRepository
     */
    private $transactionRepository;

    public function __construct(OrderTransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function pay(PaymentTransactionStruct $transaction, ApplicationContext $context): ?RedirectResponse
    {
        $data = [
            'id' => $transaction->getTransactionId(),
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
        ];
        $this->transactionRepository->update([$data], $context);

        return null;
    }

    public function finalize(string $transactionId, Request $request, ApplicationContext $context): void
    {
    }
}
