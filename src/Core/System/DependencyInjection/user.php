<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Api\UserRecoveryController;
use Shopware\Core\System\User\Api\UserValidationController;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Shopware\Core\System\User\Service\UserValidationService;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserExceptionHandler;
use Shopware\Core\System\User\Validation\UserPreWriteValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UserDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UserPreWriteValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(UserExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(UserConfigDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UserAccessKeyDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UserRecoveryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(UserRecoveryService::class)
        ->args([
            service('user_recovery.repository'),
            service('user.repository'),
            service('router'),
            service('event_dispatcher'),
            service(SalesChannelContextService::class),
            service('sales_channel.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(UserRecoveryController::class)
        ->public()
        ->args([
            service(UserRecoveryService::class),
            service('shopware.rate_limiter'),
            param('shopware.api.user_recovery.enabled'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(UserValidationService::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(UserValidationController::class)
        ->public()
        ->args([
            service(UserValidationService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
