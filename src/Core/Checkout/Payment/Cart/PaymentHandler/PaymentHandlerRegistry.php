<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

class PaymentHandlerRegistry
{
    /**
     * @var SynchronousPaymentHandlerInterface|AsynchronousPaymentHandlerInterface[]
     */
    private $handlers = [];

    public function __construct(iterable $syncHandlers, iterable $asyncHandlers)
    {
        foreach ($syncHandlers as $handler) {
            $this->addHandler($handler);
        }

        foreach ($asyncHandlers as $handler) {
            $this->addHandler($handler);
        }
    }

    public function getHandler(string $class)
    {
        if (!array_key_exists($class, $this->handlers)) {
            return null;
        }

        return $this->handlers[$class];
    }

    public function getSyncHandler(string $class): ?SynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($class);
        if (!$handler || !$handler instanceof SynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    public function getAsyncHandler(string $class): ?AsynchronousPaymentHandlerInterface
    {
        $handler = $this->getHandler($class);
        if (!$handler || !$handler instanceof AsynchronousPaymentHandlerInterface) {
            return null;
        }

        return $handler;
    }

    private function addHandler($handler): void
    {
        $this->handlers[\get_class($handler)] = $handler;
    }
}
