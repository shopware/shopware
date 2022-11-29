<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler;
use Shopware\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @package checkout
 */
class PaymentHandlerRegistry
{
    /**
     * @var array<string, PaymentHandlerInterface>
     */
    private array $handlers = [];

    private Connection $connection;

    /**
     * @internal
     *
     * @param ServiceProviderInterface<PaymentHandlerInterface> $syncHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $asyncHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $preparedHandlers
     * @param ServiceProviderInterface<PaymentHandlerInterface> $refundHandlers
     */
    public function __construct(
        ServiceProviderInterface $syncHandlers,
        ServiceProviderInterface $asyncHandlers,
        ServiceProviderInterface $preparedHandlers,
        ServiceProviderInterface $refundHandlers,
        Connection $connection
    ) {
        $this->connection = $connection;

        foreach (\array_keys($syncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $syncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($asyncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $asyncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($preparedHandlers->getProvidedServices()) as $serviceId) {
            $handler = $preparedHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (\array_keys($refundHandlers->getProvidedServices()) as $serviceId) {
            $handler = $refundHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed. Use getPaymentMethodHandler instead.
     *
     * @return PaymentHandlerInterface|null
     */
    public function getHandler(string $handlerId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getPaymentMethodHandler()')
        );

        if (!\array_key_exists($handlerId, $this->handlers)) {
            return null;
        }

        return $this->handlers[$handlerId];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getPaymentMethodHandler instead.
     *
     * @return PaymentHandlerInterface|null
     */
    public function getHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getPaymentMethodHandler()')
        );

        return $this->getPaymentMethodHandler($paymentMethod->getId());
    }

    public function getPaymentMethodHandler(
        string $paymentMethodId,
        ?string $expectedHandlerType = null
    ): ?PaymentHandlerInterface {
        $statement = $this->connection->createQueryBuilder()
            ->select('
                payment_method.handler_identifier,
                app_payment_method.id as app_payment_method_id,
                app_payment_method.pay_url,
                app_payment_method.finalize_url,
                app_payment_method.capture_url,
                app_payment_method.validate_url,
                app_payment_method.refund_url
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
            ->execute();

        $result = $statement->fetchAssociative();

        if (!$result || !\array_key_exists('handler_identifier', $result)) {
            return null;
        }

        // app payment method is set: we need to resolve an app handler
        if (isset($result['app_payment_method_id'])) {
            return $this->resolveAppPaymentMethodHandler($result, $expectedHandlerType);
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

    public function getSyncPaymentHandler(string $paymentMethodId): ?SynchronousPaymentHandlerInterface
    {
        $handler = $this->getPaymentMethodHandler($paymentMethodId, SynchronousPaymentHandlerInterface::class);

        if (!$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getAsyncPaymentHandler(string $paymentMethodId): ?AsynchronousPaymentHandlerInterface
    {
        $handler = $this->getPaymentMethodHandler($paymentMethodId, AsynchronousPaymentHandlerInterface::class);

        if (!$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getPreparedPaymentHandler(string $paymentMethodId): ?PreparedPaymentHandlerInterface
    {
        $handler = $this->getPaymentMethodHandler($paymentMethodId, PreparedPaymentHandlerInterface::class);

        if (!$handler instanceof PreparedPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getRefundPaymentHandler(string $paymentMethodId): ?RefundPaymentHandlerInterface
    {
        $handler = $this->getPaymentMethodHandler($paymentMethodId, RefundPaymentHandlerInterface::class);

        if (!$handler instanceof RefundPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getSyncPaymentHandler instead.
     */
    public function getSyncHandler(string $handlerId): ?SynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getSyncPaymentHandler()')
        );

        $handler = $this->getPaymentMethodHandler($handlerId);
        if (!$handler || !$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getAsyncPaymentHandler instead.
     */
    public function getAsyncHandler(string $handlerId): ?AsynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getAsyncPaymentHandler()')
        );

        $handler = $this->getPaymentMethodHandler($handlerId);
        if (!$handler || !$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getSyncPaymentHandler instead.
     */
    public function getSyncHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?SynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getSyncPaymentHandler()')
        );

        return $this->getSyncPaymentHandler($paymentMethod->getId());
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getAsyncPaymentHandler instead.
     */
    public function getAsyncHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?AsynchronousPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getAsyncPaymentHandler()')
        );

        return $this->getAsyncPaymentHandler($paymentMethod->getId());
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getPreparedPaymentHandler instead.
     */
    public function getPreparedHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?PreparedPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getPreparedPaymentHandler()')
        );

        return $this->getPreparedPaymentHandler($paymentMethod->getId());
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getRefundPaymentHandler instead.
     */
    public function getRefundHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?RefundPaymentHandlerInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'getRefundPaymentHandler()')
        );

        return $this->getRefundPaymentHandler($paymentMethod->getId());
    }

    private function resolveAppPaymentMethodHandler(
        array $appPaymentMethod,
        ?string $expectedHandlerType = null
    ): ?PaymentHandlerInterface {
        // validate if prepared and refund handlers have all information set
        if ($expectedHandlerType) {
            if (\is_a(PreparedPaymentHandlerInterface::class, $expectedHandlerType, true)) {
                if (empty($appPaymentMethod['capture_url']) || empty($appPaymentMethod['validate_url'])) {
                    return null;
                }
            }

            if (\is_a(RefundPaymentHandlerInterface::class, $expectedHandlerType, true)) {
                if (empty($appPaymentMethod['refund_url'])) {
                    return null;
                }
            }
        }

        if (empty($appPaymentMethod['finalize_url'])) {
            return $this->handlers[AppSyncPaymentHandler::class] ?? null;
        }

        return $this->handlers[AppAsyncPaymentHandler::class] ?? null;
    }
}
