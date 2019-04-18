[titleEn]: <>(Payment plugin)
[metaDescriptionEn]: <>(Payments are an essential part of the checkout process. That's the reason why Shopware offers an easy platform on which you can build payment plugins. Learn here, how that's done.)

Payments are an essential part of the checkout process.
That's the reason why Shopware offers an easy platform on which you can build payment plugins.

## Payment handler

Shopware platform has a few default payment handler which can be found under `Shopware\Core\Checkout\Payment\Cart\PaymentHandler`. 

## Creating a custom payment handler

You can create your own payment handler by implementing one of the following interfaces:

|               Interface             |   DI container tag            |                               Usage                                 |
|-------------------------------------|-------------------------------|---------------------------------------------------------------------|
| SynchronousPaymentHandlerInterface  | shopware.payment.method.sync  | A redirect to an external payment provider is required, e.g. PayPal |
| AsynchronousPaymentHandlerInterface | shopware.payment.method.async | Payment can be handled locally, e.g. SEPA payment                   |

Depending on the interface, those two methods are required:

* `pay`: This method will be called after an order has been placed.
You receive a `Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct|Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct` which contains the transactionId,
order details, the amount for the transaction, a return URL, payment method information and language information.
Please be aware, Shopware platform supports multiple transactions and you have to use the amount provided and not the total order amount.
If you're using the `AsynchronousPaymentHandlerInterface`, the `pay` method has to return a `RedirectResponse` to redirect the customer to an external payment provider.
Note: The `AsyncPaymentTransactionStruct` contains a return URL.
Pass this URL to the external payment provider to ensure that the customer will be redirected back to the shop to this URL.
If an error occurs while e.g. calling the API of your external payment provider you should throw an `AsyncPaymentProcessException`.
Shopware will handle this exception and set the transaction to the `cancelled` state.
The same happens if you are using the `SynchronousPaymentHandlerInterface`: throw a `SyncPaymentProcessException` in an error case.

* `finalize`: The `finalize` method is only required if you implemented the `AsynchronousPaymentHandlerInterface`,
returned a `RedirectResponse` in your `pay` method and the customer has been redirected from the payment provider back to the Shopware platform. 
You must check here if the payment was successful or not and update the order transaction state accordingly.
Similar to the pay action you are able to throw exceptions if some error cases occur.
Throw the `CustomerCanceledAsyncPaymentException` if the customer canceled the payment process on the payment provider site.
If another general error occurs throw the `AsyncPaymentFinalizeException` e.g. if your call to the payment provider API fails.
Again Shopware will handle these exceptions and will set the transaction to the `cancelled` state.

You also need to make sure to register your custom payment in the DI container.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\PaymentPlugin\Service\ExamplePayment">
            <tag name="shopware.payment.method.async" />
        </service>
    </services>
</container>
```

The mentioned example class `Swag\PaymentPlugin\Service\ExamplePayment` is created in the next step.

### Asynchronous example

An implementation of your custom asynchronous payment handler could look like this:

```php
<?php declare(strict_types=1);

namespace Swag\PaymentPlugin\Service;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ExamplePayment implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepo,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(AsyncPaymentTransactionStruct $transaction, Context $context): RedirectResponse
    {
        // Method that sends the return URL to the external gateway and gets a redirect URL back
        try {
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction->getReturnUrl());
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, Context $context): void
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the PayPal page'
            );
        }

        $paymentState = $request->query->getAlpha('status');

        if ($paymentState === 'completed') {
            // Payment completed, set transaction status to "paid"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
                OrderTransactionStates::STATE_MACHINE,
                OrderTransactionStates::STATE_PAID,
                $context
            )->getId();
        } else {
            // Payment not completed, set transaction status to "open"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
                OrderTransactionStates::STATE_MACHINE,
                OrderTransactionStates::STATE_OPEN,
                $context
            )->getId();
        }

        $transactionUpdate = [
            'id' => $transactionId,
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transactionUpdate], $context);
    }

    private function sendReturnUrlToExternalGateway(string $getReturnUrl): string
    {
        $paymentProviderUrl = '';

        // Do some API Call to your payment provider

        return $paymentProviderUrl;
    }
}
```

### Synchronous example

In this example, changing the `stateId` of the order should already be done in the `pay` method, since there will be no `finalize` method.
If you have to execute some logic which might fail, e.g. a call to an external API, you should throw a `SyncPaymentProcessException`
Shopware will handle this exception and set the transaction to the `cancelled` state.

```php
<?php declare(strict_types=1);

namespace PaymentPlugin\Service;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class ExamplePayment implements SynchronousPaymentHandlerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepo;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(EntityRepositoryInterface $orderTransactionRepo, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(SyncPaymentTransactionStruct $transaction, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PAID,
            $context
        )->getId();

        $transactionData = [
            'id' => $transaction->getOrderTransaction()->getId(),
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepo->update([$transactionData], $context);

        return null;
    }
}
```

## Setting up new payment method

The handler itself is not used yet, since there is no payment method actually using the handler mentioned above.
The payment method can be added to the system while installing your plugin.

An example for your plugin could look like this:
```php
<?php declare(strict_types=1);

namespace Swag\PaymentPlugin;

use Swag\PaymentPlugin\Service\ExamplePayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentPlugin extends Plugin
{
    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass($this->getClassName(), $context);

        $examplePaymentData = [
            // payment handler will be selected by the identifier
            'handlerIdentifier' => ExamplePayment::class,
            'name' => 'Example payment',
            'description' => 'Example payment description',
            'pluginId' => $pluginId,
        ];

        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$examplePaymentData], $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', ExamplePayment::class));
        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }
}
```

## Identify your payment

You can identify your payment by the entity property `formattedHandlerIdentifier`.
It shortens the original handler identifier (php class reference):
`Custom/Payment/SEPAPayment` to `handler_custom_sepapayment`
The syntax for the shortening can be looked up in `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber`.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-payment-plugin).
