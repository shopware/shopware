<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;

class PaymentHandlerRegistry
{
    /**
     * @var SynchronousPaymentHandlerInterface[]
     */
    private $syncHandlers = [];

    /**
     * @var AsynchronousPaymentHandlerInterface[]
     */
    private $asyncHandlers = [];

    public function __construct(iterable $syncHandlers, iterable $asyncHandlers)
    {
        foreach ($syncHandlers as $key => $handler) {
            $this->addSync($handler);
        }

        foreach ($asyncHandlers as $key => $handler) {
            $this->addAsync($handler);
        }
    }

    public function getSync(string $handlerIdentifier): SynchronousPaymentHandlerInterface
    {
        if (!array_key_exists($handlerIdentifier, $this->syncHandlers)) {
            throw new UnknownPaymentMethodException($handlerIdentifier);
        }

        return $this->syncHandlers[$handlerIdentifier];
    }

    public function getAsync(string $handlerIdentifier): AsynchronousPaymentHandlerInterface
    {
        if (!array_key_exists($handlerIdentifier, $this->asyncHandlers)) {
            throw new UnknownPaymentMethodException($handlerIdentifier);
        }

        return $this->asyncHandlers[$handlerIdentifier];
    }

    private function addSync(SynchronousPaymentHandlerInterface $handler): void
    {
        $this->syncHandlers[\get_class($handler)] = $handler;
    }

    private function addAsync(AsynchronousPaymentHandlerInterface $handler): void
    {
        $this->asyncHandlers[\get_class($handler)] = $handler;
    }
}
