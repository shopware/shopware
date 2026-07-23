<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal - planned public
 */
#[Package('after-sales')]
final class DocumentGeneratedEvent extends Event implements FlowEventAware, OrderAware, SalesChannelAware
{
    final public const EVENT_NAME = 'documentV2.generated';

    /**
     * @param list<string> $formats
     */
    public function __construct(
        private readonly DocumentEntity $document,
        private readonly OrderEntity $order,
        private readonly array $formats,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getDocument(): DocumentEntity
    {
        return $this->document;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    /**
     * @return list<string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class));
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
