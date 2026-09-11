<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\Command\ExportCookieConsentLogCommand;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentLogStorageRegistry;
use Shopware\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\FilesystemCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CookieProvider::class)
        ->args([
            service(EventDispatcherInterface::class),
            service('translator'),
            service(ScriptExecutor::class),
            param('session.storage.options'),
            service(CookieProviderInterface::class)->nullOnInvalid(),
            param('shopware.cookie_consent.log_storage'),
            param('shopware.cookie_consent.retention_days'),
        ]);

    $services->set(CookieRoute::class)
        ->public()
        ->args([
            service(CookieProvider::class),
        ]);

    // Consent log storages are selected by name via shopware.cookie_consent.log_storage
    $services->set(DatabaseCookieConsentLogStorage::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.cookie_consent.log_storage', ['storage' => DatabaseCookieConsentLogStorage::NAME]);

    $services->set(FilesystemCookieConsentLogStorage::class)
        ->args([
            service('shopware.filesystem.private'),
            param('shopware.cookie_consent.filesystem_path'),
        ])
        ->tag('shopware.cookie_consent.log_storage', ['storage' => FilesystemCookieConsentLogStorage::NAME]);

    $services->set(NullCookieConsentLogStorage::class)
        ->tag('shopware.cookie_consent.log_storage', ['storage' => NullCookieConsentLogStorage::NAME]);

    $services->set(CookieConsentLogStorageRegistry::class)
        ->args([
            tagged_locator('shopware.cookie_consent.log_storage', 'storage'),
            param('shopware.cookie_consent.log_storage'),
        ]);

    $services->set(AbstractCookieConsentLogStorage::class)
        ->factory([service(CookieConsentLogStorageRegistry::class), 'getStorage']);

    $services->set(CookieConsentLogRoute::class)
        ->public()
        ->args([
            service(CookieRoute::class),
            service(AbstractCookieConsentLogStorage::class),
            service(ClockInterface::class),
            service(RateLimiter::class),
        ]);

    $services->set(CleanupCookieConsentLogTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupCookieConsentLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(AbstractCookieConsentLogStorage::class),
            service(ClockInterface::class),
            param('shopware.cookie_consent.retention_days'),
        ])
        ->tag('messenger.message_handler');

    $services->set(ExportCookieConsentLogCommand::class)
        ->args([
            service(AbstractCookieConsentLogStorage::class),
        ])
        ->tag('console.command');
};
