<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Cookie\CookieConsentConfigVersion\CookieConsentConfigVersionDefinition;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogDefinition;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CookieProvider::class)
        ->args([
            service(EventDispatcherInterface::class),
            service('translator'),
            service(ScriptExecutor::class),
            param('session.storage.options'),
            service(CookieProviderInterface::class)->nullOnInvalid(),
        ]);

    $services->set(CookieRoute::class)
        ->public()
        ->args([
            service(CookieProvider::class),
        ]);

    $services->set(CookieConsentLogDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CookieConsentConfigVersionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CookieConsentLogRoute::class)
        ->public()
        ->args([
            service(CookieRoute::class),
            service(Connection::class),
            service(EventDispatcherInterface::class),
            service(ClockInterface::class),
            service(RateLimiter::class),
            service(SystemConfigService::class),
        ]);

    $services->set(CleanupCookieConsentLogTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupCookieConsentLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');
};
