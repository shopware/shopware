<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
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
};
