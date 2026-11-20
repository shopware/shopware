<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderDeliveryPositionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'assignNextFreePosition',
        ];
    }

    public function assignNextFreePosition(EntityWriteEvent $event): void
    {
        $inserts = array_filter(
            $event->getCommandsForEntity(OrderDeliveryDefinition::ENTITY_NAME),
            static fn (WriteCommand $command) => $command instanceof InsertCommand && $command->hasField('order_id')
        );
        $insertsWithoutPosition = array_filter($inserts, static fn (WriteCommand $command) => !$command->hasField('position'));
        if ($insertsWithoutPosition === []) {
            return;
        }

        $lastPositions = $this->fetchLastPositions($insertsWithoutPosition);
        foreach ($inserts as $command) {
            if ($command->hasField('position')) {
                $order = self::getOrderKey($command);
                $lastPositions[$order] = max($lastPositions[$order] ?? 0, (int) $command->getPayload()['position']);
            }
        }

        foreach ($insertsWithoutPosition as $command) {
            $order = self::getOrderKey($command);
            $lastPositions[$order] = ($lastPositions[$order] ?? 0) + 1;
            $command->addPayload('position', $lastPositions[$order]);
        }
    }

    /**
     * @param array<WriteCommand> $commands
     *
     * @return array<string, int>
     */
    private function fetchLastPositions(array $commands): array
    {
        $orderIds = array_values(array_unique(array_map(static fn (WriteCommand $command) => $command->getPayload()['order_id'], $commands)));

        $lastPositions = $this->connection->fetchAllKeyValue(
            'SELECT CONCAT(order_id, order_version_id), MAX(position) FROM order_delivery WHERE order_id IN (:orderIds) GROUP BY order_id, order_version_id',
            ['orderIds' => $orderIds],
            ['orderIds' => ArrayParameterType::BINARY]
        );

        return array_map(intval(...), $lastPositions);
    }

    private static function getOrderKey(WriteCommand $command): string
    {
        $payload = $command->getPayload();

        return $payload['order_id'] . $payload['order_version_id'];
    }
}
