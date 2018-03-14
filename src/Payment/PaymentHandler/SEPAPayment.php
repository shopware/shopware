<?php declare(strict_types=1);

namespace Shopware\Payment\PaymentHandler;

use Shopware\Api\Order\Repository\OrderTransactionRepository;
use Shopware\Api\Order\Struct\OrderDetailStruct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Symfony\Component\HttpFoundation\Request;

class SEPAPayment implements PaymentHandlerInterface
{
    /**
     * @var OrderTransactionRepository
     */
    private $transactionRepository;

    public function __construct(OrderTransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function payAction(
        string $transactionId,
        OrderDetailStruct $order,
        float $amount,
        string $finalizeUrl,
        ShopContext $context): ?string
    {
        $transaction = [
            'id' => $transactionId,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
        ];
        $this->transactionRepository->update([$transaction], $context);

        return null;
    }

    public function finalizePaymentAction(string $transactionId, Request $request, ShopContext $context): void
    {
    }
}
