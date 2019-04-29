<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Component\EventDispatcher\Event;

class OrderDoneEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'checkout.order.done';

    /**
     * @var array
     */
    private $order;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context, array $order)
    {
        $this->order = $order;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getOrder(): array
    {
        return $this->order;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class));
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
