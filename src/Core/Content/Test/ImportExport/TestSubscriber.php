<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport;

use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('system-settings')]
class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'onImportRecordEvent',
            ImportExportAfterImportRecordEvent::class => 'onImportRecordEvent',
            ImportExportExceptionImportRecordEvent::class => 'onImportRecordEvent',
        ];
    }

    public function onImportRecordEvent(Event $event): void
    {
        // will be called on import record event
    }
}
