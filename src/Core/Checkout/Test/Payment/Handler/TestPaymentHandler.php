<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class TestPaymentHandler implements PaymentHandlerInterface
{
    public const REDIRECT_URL = 'https://shopware.com';
    public const TECHNICAL_NAME = 'test_payment_handler';

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
        return new RedirectResponse(self::REDIRECT_URL);
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
        $transaction = [
            'id' => $transactionId,
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
        ];

        $this->transactionRepository->update([$transaction], $context);
    }
}
