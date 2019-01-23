<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class DebitPayment implements PaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    public function __construct(EntityRepositoryInterface $transactionRepository)
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
