<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.php')
        ->prefix('/_wdt');
    $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.php')
        ->prefix('/_profiler');
};
