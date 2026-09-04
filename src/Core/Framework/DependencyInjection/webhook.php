<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Command\WebhookDrainToAsyncCommand;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;
use Shopware\Core\Framework\Webhook\Health\WebhookHealthTick;
use Shopware\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Hookable\WriteResultMerger;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Shopware\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Shopware\Core\Framework\Webhook\Transport\WebhookTransportFactory;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\Validation\WebhookUrlWriteValidator;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(WebhookDispatcher::class)
        ->decorate('event_dispatcher', null, 100)
        ->args([
            service(WebhookDispatcher::class . '.inner'),
            service(WebhookManager::class),
        ]);

    $services->set(WebhookLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('shopware.webhook.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 20,
                'connect_timeout' => 10,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('after', [
                        'allow_redirects',
                        service('shopware.webhook.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopware.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(WebhookClient::class)
        ->args([
            service('shopware.webhook.guzzle'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set('shopware.webhook.trusted_url_resolver', TrustedUrlResolver::class)
        ->args([
            null,
            true,
            param('shopware.app_system.allowed_private_ip_addresses'),
        ]);

    $services->set('shopware.webhook.guzzle.security_middleware', AppSystemHttpMiddleware::class)
        ->args([
            service('shopware.webhook.trusted_url_resolver'),
            param('shopware.app_system.allow_unencrypted_traffic'),
            true,
            param('shopware.app_system.allowed_private_ip_addresses'),
            param('shopware.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookTargetValidator::class)
        ->args([
            param('shopware.app_system.allow_unencrypted_traffic'),
            param('shopware.app_system.allowed_private_ip_addresses'),
            service('shopware.webhook.trusted_url_resolver'),
            param('shopware.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookUrlWriteValidator::class)
        ->args([
            service(WebhookTargetValidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookOutboxStore::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(RetryDelayCalculator::class)
        ->args([
            service(SymfonyClockInterface::class),
        ]);

    $services->set(StreamLockService::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(WebhookHealthService::class)
        ->args([
            service(Connection::class),
            service(WebhookOutboxStore::class),
            service(HealthConfig::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(MySQLWebhookReceiver::class)
        ->args([
            service(StreamLockService::class),
            service(WebhookOutboxStore::class),
            service(SymfonyClockInterface::class),
            service('logger'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WebhookTransportFactory::class)
        ->args([
            service(WebhookOutboxStore::class),
            service_closure('messenger.transport.async'),
            service_closure(MySQLWebhookReceiver::class),
            service(WebhookHealthTick::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(WebhookHealthTick::class)
        ->args([
            service(AbstractKeyValueStorage::class),
            service(SymfonyClockInterface::class),
            service('logger'),
            service(WebhookHealthService::class),
        ]);

    $services->set(WebhookDrainToAsyncCommand::class)
        ->args([
            service(Connection::class),
            service(MessageBusInterface::class),
            service('logger'),
        ])
        ->tag('console.command');

    $services->set(WebhookManager::class)
        ->lazy()
        ->args([
            service(WebhookLoader::class),
            service(HookableEventFactory::class),
            service(AppLocaleProvider::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookClient::class),
            service(MessageBusInterface::class),
            env('APP_URL'),
            param('kernel.shopware_version'),
            param('shopware.admin_worker.enable_admin_worker'),
            service(WebhookDeliveryService::class),
            service(WebhookOutboxStore::class),
            service(WebhookHealthService::class),
        ]);

    $services->set(WebhookCacheClearer::class)
        ->args([
            service(WebhookManager::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(HookableEventFactory::class)
        ->lazy()
        ->args([
            service(BusinessEventEncoder::class),
            service(WriteResultMerger::class),
            service(HookableEventCollector::class),
        ]);

    $services->set(WriteResultMerger::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(BusinessEventEncoder::class)
        ->args([
            service(JsonEntityEncoder::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(WebhookDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(WebhookEventLogDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(HookableEventCollector::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('shopware.entity.hookable'),
            tagged_iterator('shopware.hookable_event.describer'),
        ]);

    $services->set(CoreHookableEventDescriber::class)
        ->tag('shopware.hookable_event.describer');

    $services->set(WebhookSigningSecretResolver::class)
        ->args([
            service(Connection::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(WebhookDeliveryService::class)
        ->args([
            service(WebhookClient::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookSigningSecretResolver::class),
            service(WebhookOutboxStore::class),
            service(RetryDelayCalculator::class),
            service(MessageBusInterface::class),
            service(WebhookHealthService::class),
            service('logger'),
            service(HttpErrorClassifier::class),
            param('shopware.admin_worker.enable_admin_worker'),
            param('shopware.webhook.failure_strategy'),
        ]);

    $services->set(WebhookEventMessageHandler::class)
        ->args([
            service(WebhookClient::class),
            service(WebhookHealthService::class),
            service(WebhookOutboxStore::class),
            service(WebhookDeliveryService::class),
            service('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(RetryWebhookMessageFailedSubscriber::class)
        ->args([
            service(Connection::class),
            service(WebhookOutboxStore::class),
            param('shopware.webhook.failure_strategy'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookCleanup::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
            service(StreamLockService::class),
            service(SymfonyClockInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(CleanupWebhookEventLogTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupWebhookEventLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(WebhookCleanup::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(HealthConfig::class)
        ->args([
            param('shopware.webhook.health.cooldown_schedule_seconds'),
            param('shopware.webhook.health.degraded_threshold_count'),
            param('shopware.webhook.health.non_transient_threshold_count'),
        ]);

    $services->set(HttpErrorClassifier::class);
};
