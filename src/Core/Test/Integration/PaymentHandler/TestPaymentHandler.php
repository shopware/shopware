<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\PaymentHandler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @codeCoverageIgnore this is only a fixture for the payment handler integration tests
 */
#[Package('checkout')]
class TestPaymentHandler extends AbstractPaymentHandler
{
    final public const REDIRECT_URL = 'https://shopware.com';

    public function __construct(private readonly OrderTransactionStateHandler $transactionStateHandler)
    {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function validate(
        Cart $cart,
        RequestDataBag $dataBag,
        SalesChannelContext $context
    ): ?Struct {
        if ($dataBag->getBoolean('fail')) {
            throw PaymentException::validatePreparedPaymentInterrupted('this is supposed to fail');
        }

        return new ArrayStruct(['testValue']);
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        if ($request->request->getBoolean('fail')) {
            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'Async Test Payment failed'
            );
        }

        $this->transactionStateHandler->process($transaction->getOrderTransactionId(), $context);

        if ($request->request->getBoolean('noredirect')) {
            return null;
        }

        return new RedirectResponse(self::REDIRECT_URL);
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        if ($request->query->getBoolean('cancel')) {
            throw PaymentException::customerCanceled(
                $transaction->getOrderTransactionId(),
                'Async Test Payment canceled'
            );
        }

        if ($request->query->getBoolean('fail')) {
            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getOrderTransactionId(),
                'Async Test Payment failed'
            );
        }

        $this->transactionStateHandler->paid($transaction->getOrderTransactionId(), $context);
    }
}
