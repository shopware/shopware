<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\RefundHandler;

use Symfony\Contracts\Service\ServiceProviderInterface;

class PaymentRefundHandlerRegistry
{
    /**
     * @var PaymentRefundHandlerInterface[]
     */
    private $handlers = [];

    public function __construct(ServiceProviderInterface $refundHandlers)
    {
        foreach (array_keys($refundHandlers->getProvidedServices()) as $serviceId) {
            $handler = $refundHandlers->get($serviceId);
            $this->handlers[$serviceId] = $handler;
        }
    }

    public function getRefundHandler(string $handlerId): ?PaymentRefundHandlerInterface
    {
        return $this->handlers[$handlerId] ?? null;
    }
}
