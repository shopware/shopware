<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler;
use Shopware\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler;
use Symfony\Contracts\Service\ServiceProviderInterface;

class PaymentHandlerRegistry
{
    /**
     * @var array<string, SynchronousPaymentHandlerInterface|AsynchronousPaymentHandlerInterface>
     */
    private array $handlers = [];

    public function __construct(ServiceProviderInterface $syncHandlers, ServiceProviderInterface $asyncHandlers)
    {
        foreach (array_keys($syncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $syncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }

        foreach (array_keys($asyncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $asyncHandlers->get($serviceId);
            $this->handlers[(string) $serviceId] = $handler;
        }
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getHandlerForPaymentMethod instead.
     *
     * @return AsynchronousPaymentHandlerInterface|SynchronousPaymentHandlerInterface|null
     */
    public function getHandler(string $handlerId)
    {
        if (!\array_key_exists($handlerId, $this->handlers)) {
            return null;
        }

        return $this->handlers[$handlerId];
    }

    /**
     * @return AsynchronousPaymentHandlerInterface|SynchronousPaymentHandlerInterface|null
     */
    public function getHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod)
    {
        if ($paymentMethod->getAppPaymentMethod() !== null) {
            return $this->resolveAppHandler($paymentMethod);
        }

        $handlerId = $paymentMethod->getHandlerIdentifier();

        if (!\array_key_exists($handlerId, $this->handlers)) {
            return null;
        }

        return $this->handlers[$handlerId];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getSyncHandlerForPaymentMethod instead.
     */
    public function getSyncHandler(string $handlerId): ?SynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($handlerId);
        if (!$handler || !$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getSyncHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?SynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandlerForPaymentMethod($paymentMethod);
        if (!$handler || !$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed. Use getAsyncHandlerForPaymentMethod instead.
     */
    public function getAsyncHandler(string $handlerId): ?AsynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($handlerId);
        if (!$handler || !$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getAsyncHandlerForPaymentMethod(PaymentMethodEntity $paymentMethod): ?AsynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandlerForPaymentMethod($paymentMethod);
        if (!$handler || !$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    /**
     * @return AsynchronousPaymentHandlerInterface|SynchronousPaymentHandlerInterface|null
     */
    private function resolveAppHandler(PaymentMethodEntity $paymentMethod)
    {
        $appPaymentMethod = $paymentMethod->getAppPaymentMethod();
        if ($appPaymentMethod === null) {
            return null;
        }

        if (empty($appPaymentMethod->getFinalizeUrl())) {
            return $this->handlers[AppSyncPaymentHandler::class] ?? null;
        }

        return $this->handlers[AppAsyncPaymentHandler::class] ?? null;
    }
}
