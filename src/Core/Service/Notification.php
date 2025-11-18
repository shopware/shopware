<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Notification
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function newServicesInstalled(): void
    {
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'positive',
                'message' => 'New services have been installed. Reload your administration to see what\'s new.',
                'adminOnly' => true,
                'requiredPrivileges' => ['system.plugin_maintain'],
            ],
            Context::createDefaultContext()
        );
    }
}
