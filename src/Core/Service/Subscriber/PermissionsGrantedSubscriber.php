<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsGrantedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Privileges $privileges,
        private Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionsGrantedEvent::class => 'grantPermissionsToInstalledServices',
        ];
    }

    public function grantPermissionsToInstalledServices(PermissionsGrantedEvent $event): void
    {
        $this->privileges->acceptAllForApps($this->loadInstalledServiceIds(), $event->getContext());
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
