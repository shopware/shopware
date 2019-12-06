<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AsyncTestExceptionPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    public const REDIRECT_URL = 'https://shopware.com';

    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        if ($dataBag->has('fail')) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'fail');
        }

        return new RedirectResponse(self::REDIRECT_URL);
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId(), '');
    }
}
