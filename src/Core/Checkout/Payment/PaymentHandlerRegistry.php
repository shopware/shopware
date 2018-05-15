<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment;

use Shopware\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Checkout\Payment\PaymentHandler\PaymentHandlerInterface;

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

    public function get(string $class)
    {
        if (!array_key_exists($class, $this->handlers)) {
            throw new UnknownPaymentMethodException($class);
        }

        return $this->handlers[$class];
    }

    private function add(PaymentHandlerInterface $handler)
    {
        $this->handlers[\get_class($handler)] = $handler;
    }
}
