<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\ArrayType;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires once per live order update, including aggregate child writes (line items,
 * deliveries, addresses) mapped to their order. changedFields names the written fields
 * (child fields prefixed: lineItems.*, deliveries.*, addresses.*); it is a delta hint,
 * not a value diff.
 */
#[Package('checkout')]
class OrderUpdatedEvent extends Event implements OrderAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.order.updated';

    private ?OrderEntity $order = null;

    /**
     * @param \Closure(): OrderEntity $orderLoader
     * @param list<string> $changedFields
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $orderId,
        private readonly \Closure $orderLoader,
        private readonly array $changedFields,
        private readonly ?string $salesChannelId = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('changedFields', new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order ??= ($this->orderLoader)();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // No recipient is resolved here. Throwing MailEventConfigurationException — the
        // trunk idiom for an event without an inherent recipient — would route through
        // MailStorer's OrderAware fallback, which lazy-loads the order during dispatch.
        // Because this event fires on every order write, that load fails in transient
        // contexts (order recalculation; a migration writing orders before the schema is
        // complete). A flow's send-mail action configures the recipient explicitly.
        return new MailRecipientStruct([]);
    }

    /**
     * @return list<string>
     */
    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            OrderAware::ORDER_ID => $this->orderId,
            'changedFields' => $this->changedFields,
        ];
    }
}
