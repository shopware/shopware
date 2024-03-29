<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AvailableStockMirrorSubscriber
{
    public function __invoke(EntityWriteEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $commands = $this->getAffected($event);

        foreach ($commands as $command) {
            $command->addPayload('available_stock', $command->getPayload()['stock'] ?? 0);
        }
    }

    /**
     * @return array<WriteCommand>
     */
    private function getAffected(EntityWriteEvent $event): array
    {
        return array_filter($event->getCommandsForEntity(ProductDefinition::ENTITY_NAME), static function (WriteCommand $command) {
            if ($command instanceof DeleteCommand) {
                return false;
            }

            if ($command instanceof InsertCommand) {
                return true;
            }

            if ($command->hasField('stock')) {
                return true;
            }

            return false;
        });
    }
}
