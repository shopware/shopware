<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[Package('checkout')]
class PaymentHandlerRegistry
{
    /**
     * @var array<string, PaymentHandlerInterface|AbstractPaymentHandler>
     */
    private array $handlers = [];

    /**
     * @internal
     *
     * @param ServiceProviderInterface<AbstractPaymentHandler> $paymentHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $syncHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $asyncHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $preparedHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $refundHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $recurringHandlers
     *
     * @phpstan-ignore-next-line all providers with payment interfaces will be removed
     */
    public function __construct(
        ServiceProviderInterface $paymentHandlers,
        ServiceProviderInterface $syncHandlers,
        ServiceProviderInterface $asyncHandlers,
        ServiceProviderInterface $preparedHandlers,
        ServiceProviderInterface $refundHandlers,
        ServiceProviderInterface $recurringHandlers,
        private readonly Connection $connection
    ) {
        foreach (\array_keys($paymentHandlers->getProvidedServices()) as $serviceId) {
            $handler = $paymentHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        if (Feature::isActive('v6.7.0.0')) {
            return;
        }

        // @deprecated tag:v6.7.0 - all following can be removed
        foreach (\array_keys($syncHandlers->getProvidedServices()) as $serviceId) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                \sprintf('The tag `shopware.payment.method.sync` is deprecated for service %s and will be removed in 6.7.0. Use `shopware.payment.method` instead.', $serviceId),
            );
            $handler = $syncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($asyncHandlers->getProvidedServices()) as $serviceId) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                \sprintf('The tag `shopware.payment.method.async` is deprecated for service %s and will be removed in 6.7.0. Use `shopware.payment.method` instead.', $serviceId),
            );
            $handler = $asyncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($preparedHandlers->getProvidedServices()) as $serviceId) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                \sprintf('The tag `shopware.payment.method.prepared` is deprecated for service %s and will be removed in 6.7.0. Use `shopware.payment.method` instead.', $serviceId),
            );
            $handler = $preparedHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($refundHandlers->getProvidedServices()) as $serviceId) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                \sprintf('The tag `shopware.payment.method.refund` is deprecated for service %s and will be removed in 6.7.0. Use `shopware.payment.method` instead.', $serviceId),
            );
            $handler = $refundHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($recurringHandlers->getProvidedServices()) as $serviceId) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                \sprintf('The tag `shopware.payment.method.recurring` is deprecated for service %s and will be removed in 6.7.0. Use `shopware.payment.method` instead.', $serviceId),
            );
            $handler = $recurringHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }
    }

    /**
     * @deprecated tag:v6.7.0 - reason:parameter-change - parameter `expectedHandlerType` will be removed
     */
    public function getPaymentMethodHandler(
        string $paymentMethodId,
        ?string $expectedHandlerType = null
    ): PaymentHandlerInterface|AbstractPaymentHandler|null {
        if ($expectedHandlerType !== null) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
            );
        }

        $result = $this->connection->createQueryBuilder()
            ->select('
                payment_method.handler_identifier,
                app_payment_method.id as app_payment_method_id
            ')
            ->from('payment_method')
            ->leftJoin(
                'payment_method',
                'app_payment_method',
                'app_payment_method',
                'payment_method.id = app_payment_method.payment_method_id'
            )
            ->andWhere('payment_method.id = :paymentMethodId')
            ->setParameter('paymentMethodId', Uuid::fromHexToBytes($paymentMethodId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$result || !\array_key_exists('handler_identifier', $result)) {
            return null;
        }

        // app payment method is set: we need to resolve an app handler
        if (isset($result['app_payment_method_id'])) {
            return $this->handlers[AppPaymentHandler::class] ?? null;
        }

        $handlerIdentifier = $result['handler_identifier'];

        if (!\array_key_exists($handlerIdentifier, $this->handlers)) {
            return null;
        }

        $handler = $this->handlers[$handlerIdentifier];

        // a specific handler type was requested
        if ($expectedHandlerType !== null && !\is_a($handler, $expectedHandlerType, true)) {
            return null;
        }

        return $this->handlers[$handlerIdentifier];
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `supports` of `AbstractPaymentHandler` for clear typing instead
     */
    public function getSyncPaymentHandler(string $paymentMethodId): ?SynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

        $handler = $this->getPaymentMethodHandler($paymentMethodId, SynchronousPaymentHandlerInterface::class);

        if (!$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `supports` of `AbstractPaymentHandler` for clear typing instead
     */
    public function getAsyncPaymentHandler(string $paymentMethodId): ?AsynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

        $handler = $this->getPaymentMethodHandler($paymentMethodId, AsynchronousPaymentHandlerInterface::class);

        if (!$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `supports` of `AbstractPaymentHandler` for clear typing instead
     */
    public function getPreparedPaymentHandler(string $paymentMethodId): ?PreparedPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

        $handler = $this->getPaymentMethodHandler($paymentMethodId, PreparedPaymentHandlerInterface::class);

        if (!$handler instanceof PreparedPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `supports` of `AbstractPaymentHandler` for clear typing instead
     */
    public function getRefundPaymentHandler(string $paymentMethodId): ?RefundPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

        $handler = $this->getPaymentMethodHandler($paymentMethodId, RefundPaymentHandlerInterface::class);

        if (!$handler instanceof RefundPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, use `supports` of `AbstractPaymentHandler` for clear typing instead
     */
    public function getRecurringPaymentHandler(string $paymentMethodId): ?RecurringPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

        $handler = $this->getPaymentMethodHandler($paymentMethodId, RecurringPaymentHandlerInterface::class);

        if (!$handler instanceof RecurringPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }
}
