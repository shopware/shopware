<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class StockSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeExportRecordEvent::class => 'onExport',
        ];
    }

    public function onExport(ImportExportBeforeExportRecordEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== 'product') {
            return;
        }

        $keys = $event->getConfig()->getMapping()->getKeys();
        if (!\in_array('stock', $keys, true)) {
            return;
        }

        $record = $event->getRecord();
        ++$record['stock'];
        $event->setRecord($record);
    }
}
