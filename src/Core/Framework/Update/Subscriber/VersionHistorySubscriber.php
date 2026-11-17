<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Subscriber;

use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Services\VersionHistory;
use Shopware\Core\Framework\Update\Services\VersionHistoryEntryType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class VersionHistorySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VersionHistory $versionHistory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemInstallCompletedEvent::class => 'onInstallCompleted',
            UpdatePostFinishEvent::class => 'onUpdateFinished',
        ];
    }

    public function onInstallCompleted(): void
    {
        $this->versionHistory->record(VersionHistoryEntryType::INSTALL);
    }

    public function onUpdateFinished(): void
    {
        $this->versionHistory->record(VersionHistoryEntryType::UPDATE);
    }
}
