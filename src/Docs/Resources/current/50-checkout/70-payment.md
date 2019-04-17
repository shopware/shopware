[titleEn]: <>(Payment)
[titleDe]: <>(Payment)
[wikiUrl]: <>(../checkout/payment?category=shopware-platform-en/checkout)

Payments are an essential part of the checkout process. That's the reason why Shopware offers an easy platform
on which you can build payment plugins.

## Payment handler

Shopware has a few default payment handler which can be found under 
`Shopware\Core\Checkout\Payment\Cart\PaymentHandler`. 

## Creating a custom payment handler

You can create your own payment handler by implementing the 
`Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface` 
and adding the `shopware.payment.method` tag.

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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use SwagPayPal\PayPal\Payment\PaymentBuilderInterface;
use SwagPayPal\PayPal\PaymentStatus;
use SwagPayPal\PayPal\Resource\PaymentResource;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PayPalPayment implements PaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     * @var PaymentResource
     */
    private $paymentResource;

    /**
     * @var PaymentBuilderInterface
     */
    private $paymentBuilder;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepo,
        PaymentResource $paymentResource,
        PaymentBuilderInterface $paymentBuilder,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->paymentResource = $paymentResource;
        $this->paymentBuilder = $paymentBuilder;
        $this->stateMachineRegistry = $stateMachineRegistry;
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
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_CANCELLED, $context)->getId();

            $transaction = [
                'id' => $transactionId,
                'stateId' => $stateId,
            ];
            $this->orderTransactionRepo->update([$transaction], $context);

            return;
        }

        $payerId = $request->query->get('PayerID');
        $paymentId = $request->query->get('paymentId');
        $response = $this->paymentResource->execute($payerId, $paymentId, $context);

        $paymentState = $this->getPaymentState($response);

        if ($paymentState === PaymentStatus::PAYMENT_COMPLETED) {
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();
        } else {
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_OPEN, $context)->getId();
        }

        $transaction = [
            'id' => $transactionId,
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transaction], $context);
    }
}
```
