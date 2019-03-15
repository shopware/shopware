[titleEn]: <>(Payment plugin)
[wikiUrl]: <>(../how-to/payment-plugin?category=platform-en/how-to)

Payments are an essential part of the checkout process. That's the reason why Shopware offers an easy platform on which you can build payment plugins.

## Payment handler

Shopware platform has a few default payment handler which can be found under `Shopware\Core\Checkout\Payment\Cart\PaymentHandler`. 

## Creating a custom payment handler

You can create your own payment handler by implementing the `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface` 
and adding the `payment.method` tag.

The interface requires two methods:

* `pay`: This method will be called after an order has been placed. 
You receive a `Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct` which contains the transactionId, order details, the amount for the transaction, a return URL, 
payment method information and language information.
Please be aware, Shopware platform supports multiple transactions and you have to use the amount provided and not the total order amount.
The `pay` method can return a `RedirectResponse` to redirect the customer to an external payment provider.
Note: The `PaymentTransactionStruct` contains a return URL. Pass this URL to the external payment provider to ensure that the customer will be redirected to this URL.

* `finalize`: The `finalize` method will only be called if you returned a `RedirectResponse` in your `pay` method and the customer has been redirected from the payment provider back to the Shopware platform. 
You must check here if the payment was successful or not and update the order transaction state accordingly.

An implementation of your custom payment handler could look like this:

```php
<?php declare(strict_types=1);

namespace PaymentPlugin\Service;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Defaults;
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

    public function __construct(EntityRepositoryInterface $orderTransactionRepo, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderTransactionRepo = $orderTransactionRepo;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function pay(PaymentTransactionStruct $transaction, Context $context): RedirectResponse
    {
        // Method that sends the return URL to the external gateway and gets a redirect URL back
        $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction->getReturnUrl());

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    public function finalize(string $transactionId, Request $request, Context $context): void
    {
        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_CANCELLED, $context)->getId();

            $transaction = [
                'id' => $transactionId,
                'stateId' => $stateId,
            ];

            $this->orderTransactionRepo->update([$transaction], $context);

            return;
        }

        $paymentState = $request->query->getAlpha('status');

        if ($paymentState === 'completed') {
            // Payment completed, set transaction status to "paid"
            $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();
        } else {
            // Payment not completed, set transaction status to "open"
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

This example is working with a redirect to an external payment provider.
If that's not necessary for your payment method, you can also return `null` instead.
In that case, changing the `stateId` of the order should be done in the `pay` method already.

```php
<?php

public function pay(PaymentTransactionStruct $transaction, Context $context): ?RedirectResponse
{
    $stateId = $this->stateMachineRegistry->getStateByTechnicalName(Defaults::ORDER_TRANSACTION_STATE_MACHINE, Defaults::ORDER_TRANSACTION_STATES_PAID, $context)->getId();

    $transactionData = [
        'id' => $transaction->getTransactionId(),
        'stateId' => $stateId,
    ];

    $this->orderTransactionRepo->update([$transactionData], $context);

    return null;
}
```

## Setting up new payment method

The handler itself is not used yet, since there is no payment method actually using the handler mentioned above.
The payment method can be added to the system while installing your plugin.

An example for your plugin could look like this:
```php
<?php declare(strict_types=1);

namespace PaymentPlugin;

use PaymentPlugin\Service\ExamplePayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Helper\PluginIdProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class PaymentPlugin extends Plugin
{
    /**
     * The technical name of the example payment method
     */
    public const PAYMENT_METHOD_NAME = 'ExamplePayment';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }

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
        $pluginId = $pluginIdProvider->getPluginIdByTechnicalName($this->getName(), $context);

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
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', self::PAYMENT_METHOD_NAME));
        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext())->getIds();

        if (!$paymentIds) {
            return null;
        }

        return $paymentIds[0];
    }
```
