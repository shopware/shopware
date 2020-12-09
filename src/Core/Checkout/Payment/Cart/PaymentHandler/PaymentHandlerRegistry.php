<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Symfony\Contracts\Service\ServiceProviderInterface;

class PaymentHandlerRegistry
{
    /**
     * @var SynchronousPaymentHandlerInterface|AsynchronousPaymentHandlerInterface[]
     */
    private $handlers = [];

    public function __construct(ServiceProviderInterface $syncHandlers, ServiceProviderInterface $asyncHandlers)
    {
        foreach (array_keys($syncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $syncHandlers->get($serviceId);
            $this->handlers[$serviceId] = $handler;
        }

        foreach (array_keys($asyncHandlers->getProvidedServices()) as $serviceId) {
            $handler = $asyncHandlers->get($serviceId);
            $this->handlers[$serviceId] = $handler;
        }
    }

    public function getHandler(string $handlerId)
    {
        if (!\array_key_exists($handlerId, $this->handlers)) {
            return null;
        }

        return $this->handlers[$handlerId];
    }

    public function getSyncHandler(string $handlerId): ?SynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($handlerId);
        if (!$handler || !$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getAsyncHandler(string $handlerId): ?AsynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($handlerId);
        if (!$handler || !$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }
}
