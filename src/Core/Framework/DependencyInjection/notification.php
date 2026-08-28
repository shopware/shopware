<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\Notification\NotificationBulkEntityExtension;
use Shopware\Core\Framework\Notification\NotificationDefinition;
use Shopware\Core\Framework\Notification\NotificationService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NotificationBulkEntityExtension::class)
        ->tag('shopware.bulk.entity.extension');

    $services->set(NotificationService::class)
        ->public()
        ->args([
            service('notification.repository'),
        ]);

    $services->set(NotificationDefinition::class)
        ->tag('shopware.entity.definition');
};
