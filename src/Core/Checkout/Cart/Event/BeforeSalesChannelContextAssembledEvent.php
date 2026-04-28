<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows the manipulation of the sales channel context options before it is assembled from the order
 *
 * @phpstan-import-type SalesChannelContextFactoryOptions from AbstractSalesChannelContextFactory
 */
#[Package('checkout')]
class BeforeSalesChannelContextAssembledEvent extends Event
{
    /**
     * @param SalesChannelContextFactoryOptions $options
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
     * @return SalesChannelContextFactoryOptions
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param SalesChannelContextFactoryOptions $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
