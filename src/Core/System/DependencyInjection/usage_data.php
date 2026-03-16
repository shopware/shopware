<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore - DI wiring only
 */

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider as FrameworkShopIdProvider;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Api\ConsentController;
use Shopware\Core\System\UsageData\Client\GatewayClient;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\EntitySync\CollectEntityDataMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\EntityDispatcher;
use Shopware\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessageHandler;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTask;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\GatewayStatusService;
use Shopware\Core\System\UsageData\Services\ManyToManyAssociationService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\System\UsageData\Subscriber\ConsentStateChangedSubscriber;
use Shopware\Core\System\UsageData\Subscriber\EntityDeleteSubscriber;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConsentController::class)
        ->public()
        ->args([
            new Reference(ConsentService::class),
            new Reference(BannerService::class),
        ])
        ->call('setContainer', [new Reference('service_container')]);

    $services->set(BannerService::class)
        ->args([
            new Reference('user_config.repository'),
        ]);

    $services->set(EntityDeleteSubscriber::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(Connection::class),
            new Reference('clock'),
            new Reference(ConsentService::class),
            '%shopware.usage_data.collection_enabled%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(IterateEntityMessageHandler::class)
        ->args([
            new Reference('messenger.default_bus'),
            new Reference(IterateEntitiesQueryBuilder::class),
            new Reference(ConsentService::class),
            new Reference(EntityDefinitionService::class),
            new Reference('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DispatchEntityMessageHandler::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(ManyToManyAssociationService::class),
            new Reference(UsageDataAllowListService::class),
            new Reference(Connection::class),
            new Reference(EntityDispatcher::class),
            new Reference(ConsentService::class),
            new Reference(ShopIdProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CollectEntityDataMessageHandler::class)
        ->args([
            new Reference(EntityDispatchService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(EntityDispatcher::class)
        ->args([
            new Reference('shopware.usage_data.gateway.client'),
            new Reference(InstanceService::class),
            new Reference(SystemConfigService::class),
            new Reference('clock'),
            '%kernel.environment%',
            '%shopware.usage_data.gateway.dispatch_enabled%',
        ]);

    $services->set(IterateEntitiesQueryBuilder::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(Connection::class),
            '%shopware.usage_data.gateway.batch_size%',
            new Reference('logger'),
        ]);

    $services->set(EntityDispatchService::class)
        ->lazy(true)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(AbstractKeyValueStorage::class),
            new Reference('messenger.default_bus'),
            new Reference(GatewayStatusService::class),
            new Reference(ShopIdProvider::class),
            new Reference(SystemConfigService::class),
            new Reference(ConsentService::class),
            '%shopware.usage_data.collection_enabled%',
        ]);

    $services->set(ManyToManyAssociationService::class)
        ->args([
            new Reference(Connection::class),
        ]);

    $services->set(EntityDefinitionService::class)
        ->args([
            new TaggedIteratorArgument('shopware.entity.definition'),
            new Reference(UsageDataAllowListService::class),
        ]);

    $services->set(ConsentReporter::class)
        ->args([
            new Reference('shopware.usage_data.gateway.client'),
            new Reference(ShopIdProvider::class),
            new Reference(SystemConfigService::class),
            new Reference(InstanceService::class),
            '%env(APP_URL)%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UsageDataAllowListService::class);

    $services->set(ShopIdProvider::class)
        ->args([
            new Reference(FrameworkShopIdProvider::class),
            new Reference(SystemConfigService::class),
        ]);

    $services->set(GatewayStatusService::class)
        ->args([
            new Reference(GatewayClient::class),
        ]);

    $services->set(GatewayClient::class)
        ->args([
            new Reference('shopware.usage_data.gateway.client'),
            new Reference(ShopIdProvider::class),
        ]);

    $services->set(CollectEntityDataTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CollectEntityDataTaskHandler::class)
        ->args([
            new Reference('scheduled_task.repository'),
            new Reference('logger'),
            new Reference(EntityDispatchService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set('shopware.usage_data.gateway.client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([
            [
                'base_uri' => '%shopware.usage_data.gateway.base_uri%',
            ],
        ]);

    $services->set(ConsentStateChangedSubscriber::class)
        ->args([
            new Reference(EntityDispatchService::class),
            new Reference(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');
};
