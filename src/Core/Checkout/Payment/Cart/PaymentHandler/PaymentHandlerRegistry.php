<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Symfony\Contracts\Service\ServiceProviderInterface;

class PaymentHandlerRegistry
{
    /**
     * @var SynchronousPaymentHandlerInterface|AsynchronousPaymentHandlerInterface[]
     */
    private $handlers = [];

    /** @deprecated tag:v6.3.0 TypeHint for both parameters will be changed to ServiceProviderInterface */
    public function __construct($syncHandlers, $asyncHandlers)
    {
        if ($syncHandlers instanceof ServiceProviderInterface && $asyncHandlers instanceof ServiceProviderInterface) {
            foreach (array_keys($syncHandlers->getProvidedServices()) as $serviceId) {
                $handler = $syncHandlers->get($serviceId);
                $this->handlers[$serviceId] = $handler;
            }

            foreach (array_keys($asyncHandlers->getProvidedServices()) as $serviceId) {
                $handler = $asyncHandlers->get($serviceId);
                $this->handlers[$serviceId] = $handler;
            }
        } else {
            foreach ($syncHandlers as $handler) {
                $this->addHandler($handler);
            }

            foreach ($asyncHandlers as $handler) {
                $this->addHandler($handler);
            }
        }
    }

    public function getHandler(string $handlerId)
    {
        if (!array_key_exists($handlerId, $this->handlers)) {
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

    /** @deprecated tag:v6.3.0 will be removed in 6.3.0 */
    private function addHandler($handler): void
    {
        $this->handlers[\get_class($handler)] = $handler;
    }
}
