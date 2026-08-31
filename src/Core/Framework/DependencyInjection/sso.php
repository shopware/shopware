<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\Controller\SsoController;
use Shopware\Core\Framework\Sso\LoginResponseService;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Sso\StateValidator;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SsoController::class)
        ->public()
        ->args([
            service('shopware.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(LoginConfigService::class),
            service(LoginResponseService::class),
            service(StateValidator::class),
            service(SsoUserService::class),
            service(SsoUserInvitationMailService::class),
            service(SsoService::class),
            service(RouterInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LoginConfigService::class)
        ->args([
            param('shopware.admin_login'),
            service(RouterInterface::class),
        ]);

    $services->set(ExternalTokenService::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
        ]);

    $services->set(IdTokenParser::class)
        ->args([
            service(PublicKeyLoader::class),
            service(LoginConfigService::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(PublicKeyLoader::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
            service('cache.object'),
        ]);

    $services->set(UserService::class)
        ->args([
            service(Connection::class),
            service(IdTokenParser::class),
            service('user.repository'),
            service(ExternalTokenService::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoUserService::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(LoginResponseService::class)
        ->args([
            service(RouterInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoService::class)
        ->args([
            service(LoginConfigService::class),
            service(RefreshTokenRepository::class),
        ]);

    $services->set(StateValidator::class);

    $services->set(SsoUserInvitationMailService::class)
        ->args([
            service(MailService::class),
            service(SystemConfigService::class),
            service('mail_template.repository'),
            service('mail_template_type.repository'),
            service('user.repository'),
            service('language.repository'),
            service(RouterInterface::class),
            param('APP_URL'),
        ]);
};
