<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Consent\Api\ConsentController;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\Definition;
use Shopware\Core\System\Consent\Log\ConsentChangedSubscriber;
use Shopware\Core\System\Consent\Log\ConsentLogInterface;
use Shopware\Core\System\Consent\Log\DatabaseLog;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConsentController::class)
        ->public()
        ->args([
            new Reference(ConsentService::class),
        ]);

    $services->set(ConsentRepository::class)
        ->args([
            new Reference(Connection::class),
        ]);

    $services->set(ConsentService::class)
        ->args([
            new TaggedIteratorArgument('shopware.consent.scope'),
            new TaggedIteratorArgument('shopware.consent.definition'),
            new Reference(ConsentRepository::class),
            new Reference('event_dispatcher'),
        ]);

    $services->set(ConsentScope\System::class)
        ->tag('shopware.consent.scope');

    $services->set(ConsentScope\AdminUser::class)
        ->tag('shopware.consent.scope');

    $services->set(Definition\BackendData::class)
        ->tag('shopware.consent.definition');

    $services->set(Definition\ProductAnalytics::class)
        ->tag('shopware.consent.definition');

    $services->set(ConsentLogInterface::class)
        ->class(DatabaseLog::class)
        ->args([
            new Reference(Connection::class),
        ]);

    $services->set(ConsentChangedSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(ConsentLogInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set(SetupStagingEventSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(Connection::class),
        ]);
};
