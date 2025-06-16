<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsRevokedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Privileges $privileges,
        private Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsRevokedEvent::class => 'revokePermissionsForInstalledAServices',
        ];
    }

    public function revokePermissionsForInstalledAServices(PermissionsRevokedEvent $event): void
    {
        $this->privileges->revokeAllForApps($this->loadInstalledServiceIds(), $event->getContext());
    }

    /**
     * @return list<string>
     */
    private function loadInstalledServiceIds(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM app WHERE self_managed = 1'
        );
    }
}
