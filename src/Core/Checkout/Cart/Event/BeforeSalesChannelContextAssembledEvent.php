<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows the manipulation of the sales channel context options before it is assembled from the order
 */
#[Package('checkout')]
class BeforeSalesChannelContextAssembledEvent extends Event
{
    /**
     * @param array<string, array<string, bool>|string|null> $options
     *
     * @internal
     */
    public function __construct(
        private readonly OrderEntity $order,
        private readonly Context $context,
        private array $options,
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, array<string, bool>|string|null>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, array<string, bool>|string|null> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
