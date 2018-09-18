<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;

class PaymentHandlerRegistry
{
    /**
     * @var PaymentHandlerInterface[]
     */
    private $handlers = [];

    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->add($handler);
        }
    }

    public function get(string $class): PaymentHandlerInterface
    {
        if (!array_key_exists($class, $this->handlers)) {
            throw new UnknownPaymentMethodException($class);
        }

        return $this->handlers[$class];
    }

    private function add(PaymentHandlerInterface $handler): void
    {
        $this->handlers[\get_class($handler)] = $handler;
    }
}
