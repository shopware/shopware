## Payment

Payments are an essential part of the checkout process. That's the reason why Shopware offers an easy platform
on which you can build payment plugins.

## Payment handler

Shopware has a few default payment handler which can be found under 
`Shopware\Core\Checkout\Payment\Cart\PaymentHandler`. 

## Creating a custom payment handler

You can create your own payment handler by implementing the 
`Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface` 
and adding the `payment.method` tag.

The interface contains two methods:

* `pay`: will be called after an order has been placed. 
You receive a `Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct` which contains 
the transactionId, order details, the amount of the transaction, return URL, 
payment method information and language information.  
Please be aware, Shopware supports transactions and you must use the amount provided 
and not the total order amount  
The pay method can return a `RedirectResponse` to redirect the customer to an external payment gateway.  
Note: The `PaymentTransactionStruct` contains a return URL. Pass this URL to the external payment gateway 
to ensure that the user will be redirected to this URL.

* `finalize`: will only be called if you returned a `RedirectResponse` in your `pay` method 
and the customer has been redirected from the payment gateway back to Shopware. 
You might check here if the payment was successful or not and update the order transaction state accordingly.

An implementation can look like this:
```php
<?php declare(strict_types=1);

namespace Plugin\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Plugin\Payment\PaymentStatus;
use Plugin\Payment\Resource\PaymentResource;
use Plugin\Payment\Struct\Payment\RelatedResources\RelatedResource;
use Plugin\Service\PaymentBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CustomPaymentHandler implements PaymentHandlerInterface
{
    /**
     * @var RepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var PaymentResource
     */
    private $paymentResource;

    /**
     * @var PaymentBuilderInterface
     */
    private $paymentBuilder;

    public function __construct(
        RepositoryInterface $transactionRepository,
        PaymentResource $paymentResource,
        PaymentBuilderInterface $paymentBuilder
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->paymentResource = $paymentResource;
        $this->paymentBuilder = $paymentBuilder;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
    {
        $payment = $this->paymentBuilder->getPayment($transaction, $context);

        $response = $this->paymentResource->create($payment, $context);

        return new RedirectResponse($response->getLinks()[1]->getHref());
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
        if ($request->query->getBoolean('cancel')) {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_FAILED,
            ];
            $this->transactionRepository->update([$transaction], $context);

            return;
        }

        $payerId = $request->get('PayerID');
        $paymentId = $request->get('paymentId');
        $response = $this->paymentResource->execute($payerId, $paymentId, $context);

        /** @var RelatedResource $responseSale */
        $responseSale = $response->getTransactions()->getRelatedResources()->getResources()[0];

        // apply the payment status if its completed by PayPal
        $paymentState = $responseSale->getState();

        if ($paymentState === PaymentStatus::PAYMENT_COMPLETED) {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_COMPLETED,
            ];
        } else {
            $transaction = [
                'id' => $transactionId,
                'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
            ];
        }

        $this->transactionRepository->update([$transaction], $context);
    }
}
```


